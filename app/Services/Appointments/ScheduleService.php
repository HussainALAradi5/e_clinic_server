<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Dtos\Appointments\CancelAppointmentData;
use App\Dtos\Appointments\ScheduleBlockData;
use App\Dtos\Appointments\ScheduleExceptionData;
use App\Dtos\Appointments\WorkingWindow;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Appointments\ScheduleExceptionType;
use App\Exceptions\Appointments\ScheduleHasFutureAppointmentsException;
use App\Exceptions\Appointments\ScheduleOverlapException;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Appointments\ScheduleServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ProviderSchedule;
use App\Models\Appointments\ScheduleException;
use App\Models\HR\Employee;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScheduleService implements ScheduleServiceInterface
{
    public function __construct(private readonly ClinicClock $clock) {}

    public function getWeeklySchedule(Employee $provider, ?CarbonImmutable $onDate = null): Collection
    {
        return ProviderSchedule::query()
            ->where('provider_id', $provider->id)
            ->effectiveOn($onDate ?? $this->clock->today())
            ->orderBy('day_of_week')->orderBy('start_time')
            ->get();
    }

    public function replaceWeeklySchedule(Employee $provider, array $blocks, CarbonImmutable $effectiveFrom): Collection
    {
        foreach ($blocks as $i => $block) {
            foreach (array_slice($blocks, $i + 1) as $other) {
                if ($block->overlaps($other)) {
                    throw new ScheduleOverlapException(context: ['day_of_week' => $block->dayOfWeek->value]);
                }
            }
        }

        return DB::transaction(function () use ($provider, $blocks, $effectiveFrom): Collection {
            $orphans = $this->activeBookingsFrom($provider, $effectiveFrom)->reject(
                fn (Appointment $a): bool => $this->fits($a, $this->windowsFor($provider, $a->scheduled_date, $blocks)),
            );
            if ($orphans->isNotEmpty()) {
                throw new ScheduleHasFutureAppointmentsException(context: ['appointments' => $this->summaries($orphans)]);
            }

            // Blocks starting on / after the date never applied: drop them; open ones end the day before.
            $current = ProviderSchedule::query()->where('provider_id', $provider->id)
                ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString()))
                ->get();
            foreach ($current as $old) {
                $old->effective_from->gte($effectiveFrom)
                    ? $old->softDelete()
                    : $old->forceFill(['effective_to' => $effectiveFrom->subDay()])->save();
            }

            foreach ($blocks as $block) {
                ProviderSchedule::query()->create([
                    'provider_id' => $provider->id,
                    'day_of_week' => $block->dayOfWeek,
                    'start_time' => $this->time($block->startTime),
                    'end_time' => $this->time($block->endTime),
                    'slot_minutes' => $block->slotMinutes,
                    'effective_from' => $effectiveFrom,
                ]);
            }

            return $this->getWeeklySchedule($provider, $effectiveFrom);
        });
    }

    public function listExceptions(?Employee $provider, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ScheduleException::query()
            ->where(fn (Builder $q) => $q->whereNull('provider_id')->when($provider !== null, fn (Builder $q) => $q->orWhere('provider_id', $provider?->id)))
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get();
    }

    public function addException(?Employee $provider, ScheduleExceptionData $data): ScheduleException
    {
        if ($provider === null && $data->type !== ScheduleExceptionType::Holiday) {
            throw ValidationException::withMessages(['type' => [__('validation.in', ['attribute' => 'type'])]]);
        }
        $taken = ScheduleException::query()->whereDate('date', $data->date->toDateString())
            ->when($provider === null, fn (Builder $q) => $q->whereNull('provider_id'), fn (Builder $q) => $q->where('provider_id', $provider?->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['date' => [__('validation.unique', ['attribute' => 'date'])]]);
        }

        return DB::transaction(function () use ($provider, $data): ScheduleException {
            $exception = ScheduleException::query()->create([
                'provider_id' => $provider?->id,
                'date' => $data->date,
                'type' => $data->type,
                'start_time' => $data->startTime !== null ? $this->time($data->startTime) : null,
                'end_time' => $data->endTime !== null ? $this->time($data->endTime) : null,
                'reason' => $data->reason,
            ]);

            $affected = $this->affectedBy($exception);
            if ($affected->isNotEmpty()) {
                if (! $data->cancelAffected) {
                    throw new ScheduleHasFutureAppointmentsException(context: ['appointments' => $this->summaries($affected)]);
                }
                $appointments = app(AppointmentServiceInterface::class);
                foreach ($affected as $appointment) {
                    $appointments->cancel($appointment, new CancelAppointmentData(CancelledBy::Clinic, $data->reason ?? $data->type->label()));
                }
            }

            return $exception;
        });
    }

    public function removeException(ScheduleException $exception): void
    {
        $exception->softDelete();
    }

    public function getWorkingWindows(Employee $provider, CarbonImmutable $date): Collection
    {
        return $this->windowsFor($provider, $date);
    }

    /**
     * @param  list<ScheduleBlockData>|null  $blocks  proposed week (null = stored schedule)
     * @return Collection<int, WorkingWindow>
     */
    private function windowsFor(Employee $provider, CarbonImmutable $date, ?array $blocks = null): Collection
    {
        $exceptions = ScheduleException::query()->whereDate('date', $date->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('provider_id')->orWhere('provider_id', $provider->id))
            ->get();

        if ($exceptions->contains(fn (ScheduleException $e): bool => $e->closesTheDay())) {
            return collect();
        }
        $custom = $exceptions->first(fn (ScheduleException $e): bool => $e->type === ScheduleExceptionType::CustomHours);
        if ($custom instanceof ScheduleException) {
            return collect([$this->window($date, (string) $custom->start_time, (string) $custom->end_time)]);
        }

        if ($blocks !== null) {
            return collect($blocks)
                ->filter(fn (ScheduleBlockData $b): bool => $b->dayOfWeek->value === $date->dayOfWeek)
                ->map(fn (ScheduleBlockData $b): WorkingWindow => $this->window($date, $b->startTime, $b->endTime, $b->slotMinutes))
                ->sortBy(fn (WorkingWindow $w): int => $w->start->getTimestamp())->values();
        }

        return ProviderSchedule::query()->where('provider_id', $provider->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->effectiveOn($date)
            ->orderBy('start_time')
            ->get()
            ->map(fn (ProviderSchedule $s): WorkingWindow => $this->window($date, $s->start_time, $s->end_time, $s->slot_minutes))
            ->values();
    }

    /**
     * Bookings an exception makes impossible.
     *
     * @return Collection<int, Appointment>
     */
    private function affectedBy(ScheduleException $exception): Collection
    {
        $bookings = Appointment::query()
            ->whereDate('scheduled_date', $exception->date->toDateString())
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->when($exception->provider_id !== null, fn (Builder $q) => $q->where('provider_id', $exception->provider_id))
            ->with('patient.person')
            ->get();

        if ($exception->closesTheDay()) {
            return $bookings;
        }

        $window = $this->window($exception->date, (string) $exception->start_time, (string) $exception->end_time);

        return $bookings->reject(fn (Appointment $a): bool => $window->contains($a->startsAt(), $a->endsAt()))->values();
    }

    /**
     * Pending / confirmed bookings of the provider from a date onward.
     *
     * @return Collection<int, Appointment>
     */
    private function activeBookingsFrom(Employee $provider, CarbonImmutable $from): Collection
    {
        return Appointment::query()
            ->where('provider_id', $provider->id)
            ->whereDate('scheduled_date', '>=', $from->toDateString())
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->with('patient.person')
            ->get();
    }

    /**
     * @param  Collection<int, WorkingWindow>  $windows
     */
    private function fits(Appointment $appointment, Collection $windows): bool
    {
        return $windows->contains(fn (WorkingWindow $w): bool => $w->contains($appointment->startsAt(), $appointment->endsAt()));
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array<string, string>>
     */
    private function summaries(Collection $appointments): array
    {
        return array_values($appointments->map(fn (Appointment $a): array => [
            'id' => $a->id,
            'code' => $a->code,
            'date' => $a->scheduled_date->toDateString(),
            'start_time' => substr($a->start_time, 0, 5),
            'patient' => $a->patient->person->full_name,
        ])->all());
    }

    private function window(CarbonImmutable $date, string $start, string $end, ?int $slotMinutes = null): WorkingWindow
    {
        $day = $date->toDateString();

        $tz = $this->clock->timezone();

        return new WorkingWindow(CarbonImmutable::parse("{$day} {$start}", $tz), CarbonImmutable::parse("{$day} {$end}", $tz), $slotMinutes);
    }

    /** `9:00` / `09:00` / `09:00:00` → `09:00:00` (so stored times compare as text). */
    private function time(string $value): string
    {
        return CarbonImmutable::parse($value)->format('H:i:s');
    }
}
