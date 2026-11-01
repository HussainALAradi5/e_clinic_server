<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Services;

use App\Enums\Appointments\AppointmentStatus;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Records\Contracts\MedicalRecordServiceInterface;
use App\Modules\Appointments\Records\Data\MedicalRecordData;
use App\Modules\Appointments\Records\Exceptions\MedicalRecordNotSignedException;
use App\Modules\Appointments\Records\Exceptions\MedicalRecordSignedException;
use App\Modules\Appointments\Records\Models\Icd10Code;
use App\Modules\Appointments\Records\Models\MedicalRecord;
use App\Modules\Appointments\Records\Models\RecordAddendum;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\ClinicClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Visit documentation: SOAP notes, vitals and ICD-10 diagnoses, signed by the visit's provider and then only
 * corrected through addenda.
 */
final class MedicalRecordService implements MedicalRecordServiceInterface
{
    private const RELATIONS = ['diagnoses.code', 'addenda.author.person', 'signer.person'];

    /** Vitals from check-in; notes until (and after) the visit ends, as long as nobody signed. */
    private const WRITABLE = [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed];

    public function __construct(private readonly ClinicClock $clock) {}

    public function get(Appointment $appointment): MedicalRecord
    {
        $record = MedicalRecord::query()->with(self::RELATIONS)->find($appointment->id);
        if ($record instanceof MedicalRecord) {
            return $record;
        }

        $draft = new MedicalRecord(['id' => $appointment->id]);
        $draft->setRelation('diagnoses', new EloquentCollection);
        $draft->setRelation('addenda', new EloquentCollection);
        $draft->setRelation('signer', null);

        return $draft;
    }

    public function save(Appointment $appointment, MedicalRecordData $data): MedicalRecord
    {
        if (! in_array($appointment->status, self::WRITABLE, true)) {
            throw new InvalidStateTransitionException(context: ['from' => $appointment->status->value, 'to' => 'documented']);
        }
        // The care team reads every visit of a patient; only the visit's own provider (or someone with full record
        // rights, e.g. the nurse taking vitals) writes in it.
        $actor = Auth::user();
        if (! $actor instanceof User || ($actor->id !== $appointment->provider_id && ! $actor->can('records.read_all'))) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }

        DB::transaction(function () use ($appointment, $data): void {
            $record = MedicalRecord::query()->lockForUpdate()->find($appointment->id) ?? new MedicalRecord(['id' => $appointment->id]);
            if ($record->isSigned()) {
                throw new MedicalRecordSignedException;
            }
            if ($record->exists && $data->version !== null) {
                $record->assertVersion($data->version);
            }

            $record->fill($data->values);
            $record->save();
            if ($data->diagnoses !== null) {
                $this->replaceDiagnoses($record, $data->diagnoses);
            }
        });

        return $this->get($appointment);
    }

    public function sign(Appointment $appointment): MedicalRecord
    {
        $actor = Auth::user();
        if (! $actor instanceof User || ($actor->id !== $appointment->provider_id && ! $actor->isSuperAdmin())) {
            throw new AuthorizationException(__('errors.MEDICAL_RECORD_SIGN_PROVIDER_ONLY'));
        }

        DB::transaction(function () use ($appointment, $actor): void {
            $record = MedicalRecord::query()->lockForUpdate()->find($appointment->id);
            if ($record?->isSigned() === true) {
                throw new MedicalRecordSignedException;
            }
            $empty = ! $record instanceof MedicalRecord
                || (collect(MedicalRecord::NOTES)->every(fn (string $f): bool => trim((string) $record->getAttribute($f)) === '') && ! $record->diagnoses()->exists());
            if ($empty) {
                throw ValidationException::withMessages(['assessment' => [__('errors.MEDICAL_RECORD_EMPTY')]]);
            }
            $record->forceFill(['signed_by' => $actor->id, 'signed_at' => $this->clock->now()])->save();
        });

        return $this->get($appointment);
    }

    public function addAddendum(Appointment $appointment, string $text): RecordAddendum
    {
        $record = MedicalRecord::query()->find($appointment->id);
        if (! $record instanceof MedicalRecord || ! $record->isSigned()) {
            throw new MedicalRecordNotSignedException;
        }

        return $record->addenda()->create(['text' => $text, 'created_by' => Auth::id()])->load('author.person');
    }

    public function searchIcd10(string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }
        $like = '%'.mb_strtolower($term).'%';

        return Icd10Code::query()
            ->where(fn (Builder $q) => $q->whereRaw('lower(code) like ?', [mb_strtolower($term).'%'])
                ->orWhereRaw('lower(title) like ?', [$like])
                ->orWhereRaw('lower(title_ar) like ?', [$like]))
            ->orderByRaw('CASE WHEN lower(code) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($term).'%'])
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    /**
     * Exactly one primary diagnosis when any is given (the first one unless another is marked).
     *
     * @param  list<array{icd10_code_id: string, is_primary: bool, notes: ?string}>  $diagnoses
     */
    private function replaceDiagnoses(MedicalRecord $record, array $diagnoses): void
    {
        $diagnoses = collect($diagnoses)->unique('icd10_code_id')->values();
        $primaries = $diagnoses->where('is_primary', true)->count();
        if ($primaries > 1) {
            throw ValidationException::withMessages(['diagnoses' => [__('errors.ONE_PRIMARY_DIAGNOSIS')]]);
        }

        $record->diagnoses()->delete();
        foreach ($diagnoses as $i => $diagnosis) {
            $record->diagnoses()->create([...$diagnosis, 'is_primary' => $primaries === 0 ? $i === 0 : $diagnosis['is_primary']]);
        }
    }
}
