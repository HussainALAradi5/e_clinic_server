<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Http\Controllers;

use App\Modules\Appointments\Schedules\Contracts\ScheduleServiceInterface;
use App\Modules\Appointments\Schedules\Http\Requests\ReplaceScheduleRequest;
use App\Modules\Appointments\Schedules\Http\Requests\ScheduleExceptionRequest;
use App\Modules\Appointments\Schedules\Http\Resources\ProviderScheduleResource;
use App\Modules\Appointments\Schedules\Http\Resources\ScheduleExceptionResource;
use App\Modules\Appointments\Schedules\Models\ScheduleException;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Http\Concerns\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Weekly schedule and date exceptions of a provider (`providers/{provider}/schedules`) and clinic-wide holidays
 * (`schedules/holidays`). schedules.read_own = the provider's own schedule.
 */
final class ScheduleController
{
    use ApiResponse;

    public function __construct(private readonly ScheduleServiceInterface $schedules) {}

    public function show(Request $request, string $provider): JsonResponse
    {
        $request->validate(['on_date' => ['nullable', 'date']]);
        $onDate = $request->filled('on_date') ? CarbonImmutable::parse($request->string('on_date')->toString())->startOfDay() : null;

        return $this->ok(ProviderScheduleResource::collection($this->schedules->getWeeklySchedule($this->provider($provider), $onDate)));
    }

    public function replace(ReplaceScheduleRequest $request, string $provider): JsonResponse
    {
        return $this->ok(ProviderScheduleResource::collection($this->schedules->replaceWeeklySchedule($this->provider($provider), $request->blocks(), $request->effectiveFrom())));
    }

    public function exceptions(Request $request, string $provider): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return $this->ok(ScheduleExceptionResource::collection($this->schedules->listExceptions($this->provider($provider), $from, $to)));
    }

    public function addException(ScheduleExceptionRequest $request, string $provider): JsonResponse
    {
        return $this->created(new ScheduleExceptionResource($this->schedules->addException($this->provider($provider), $request->toData())));
    }

    public function removeException(string $provider, string $exception): Response
    {
        $model = ScheduleException::query()->where('provider_id', $this->provider($provider)->id)->findOrFail($exception);
        $this->schedules->removeException($model);

        return $this->noContent();
    }

    public function holidays(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return $this->ok(ScheduleExceptionResource::collection($this->schedules->listExceptions(null, $from, $to)));
    }

    public function addHoliday(ScheduleExceptionRequest $request): JsonResponse
    {
        return $this->created(new ScheduleExceptionResource($this->schedules->addException(null, $request->toData())));
    }

    public function removeHoliday(string $exception): Response
    {
        $this->schedules->removeException(ScheduleException::query()->whereNull('provider_id')->findOrFail($exception));

        return $this->noContent();
    }

    /** Provider in the caller's scope (read_own → only themselves). */
    private function provider(string $id): Employee
    {
        $query = Employee::query()->with('user');
        $user = Auth::user();
        if ($user === null || ! $user->can('schedules.read_all')) {
            $query->whereKey($user?->getAuthIdentifier());
        }

        return $query->findOrFail($id);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.CarbonImmutable::parse((string) $request->input('from', 'today'))->addDays(92)->toDateString()],
        ]);

        return [
            CarbonImmutable::parse($request->string('from')->toString())->startOfDay(),
            CarbonImmutable::parse($request->string('to')->toString())->startOfDay(),
        ];
    }
}
