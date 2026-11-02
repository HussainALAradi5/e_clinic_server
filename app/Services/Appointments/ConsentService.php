<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Dtos\Appointments\ConsentData;
use App\Enums\Appointments\ConsentType;
use App\Exceptions\Appointments\ConsentRequiredException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Appointments\ConsentServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Patient;
use App\Models\Appointments\PatientConsent;
use App\Models\Appointments\Treatment;
use App\Models\Identity\Attachment;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Patient consents and the guard that blocks work needing one.
 */
final class ConsentService implements ConsentServiceInterface
{
    private const RELATIONS = ['explainer.person', 'witness.person', 'appointment', 'treatment', 'clinicalOrder', 'attachments'];

    private const SCOPES = ['appointment_id', 'treatment_id', 'clinical_order_id'];

    /** Below this age a parent or guardian signs. */
    private const ADULT_AGE = 18;

    public function __construct(private readonly ClinicClock $clock) {}

    public function list(Patient $patient): Collection
    {
        return $this->consents()->where('patient_id', $patient->id)->orderByDesc('signed_at')->get()->toBase();
    }

    public function find(string $id): PatientConsent
    {
        return $this->consents()->findOrFail($id);
    }

    public function record(Patient $patient, ConsentData $data): PatientConsent
    {
        $values = $data->values;
        $type = ConsentType::from((string) $values['type']);
        $scopes = collect(self::SCOPES)->filter(fn (string $s): bool => ($values[$s] ?? null) !== null);

        if (in_array($type, PatientConsent::PATIENT_WIDE, true) ? $scopes->isNotEmpty() : $scopes->count() > 1) {
            throw ValidationException::withMessages(['type' => [__('errors.CONSENT_SCOPE_INVALID')]]);
        }
        $this->assertScopeBelongsTo($patient, $values);

        $minor = $patient->person->date_of_birth->diffInYears($this->clock->today()) < self::ADULT_AGE;
        if ($minor && ($values['signer_relationship'] ?? 'self') === 'self') {
            throw ValidationException::withMessages(['signer_relationship' => [__('errors.CONSENT_GUARDIAN_REQUIRED')]]);
        }
        if (($values['witness_id'] ?? null) !== null && $values['witness_id'] === $values['explained_by']) {
            throw ValidationException::withMessages(['witness_id' => [__('validation.different', ['attribute' => 'witness', 'other' => 'explained by'])]]);
        }

        $consent = DB::transaction(function () use ($patient, $values, $data): PatientConsent {
            $consent = PatientConsent::query()->create([...$values, 'patient_id' => $patient->id]);
            if ($data->signatureAttachmentId !== null) {
                // The scan was uploaded on the patient file first; it now belongs to the consent.
                Attachment::query()->whereKey($data->signatureAttachmentId)
                    ->where('attachable_type', $patient->getMorphClass())->where('attachable_id', $patient->id)
                    ->update(['attachable_type' => $consent->getMorphClass(), 'attachable_id' => $consent->id]);
            }

            return $consent;
        });

        return $this->find($consent->id);
    }

    public function revoke(PatientConsent $consent, string $reason): PatientConsent
    {
        if ($consent->revoked_at !== null) {
            throw new InvalidStateTransitionException(context: ['from' => 'revoked', 'to' => 'revoked']);
        }
        $consent->forceFill(['revoked_at' => $this->clock->now(), 'revocation_reason' => $reason])->save();

        return $this->find($consent->id);
    }

    public function assertConsent(Patient $patient, ?ConsentType $type, ?Model $scope = null): void
    {
        if ($type === null) {
            return;
        }

        $valid = PatientConsent::query()
            ->where('patient_id', $patient->id)
            ->where('type', $type->value)
            ->validOn($this->clock->today())
            ->where(function (Builder $q) use ($scope): void {
                $q->where(fn (Builder $wide) => $wide->whereNull('appointment_id')->whereNull('treatment_id')->whereNull('clinical_order_id'));
                if ($scope instanceof Appointment) {
                    $q->orWhere('appointment_id', $scope->id);
                    if ($scope->treatment_id !== null) {
                        $q->orWhere('treatment_id', $scope->treatment_id);
                    }
                } elseif ($scope instanceof ClinicalOrder) {
                    $q->orWhere('clinical_order_id', $scope->id);
                } elseif ($scope instanceof Treatment) {
                    $q->orWhere('treatment_id', $scope->id);
                }
            })
            ->exists();

        if (! $valid) {
            throw new ConsentRequiredException(__('errors.CONSENT_REQUIRED_TYPE', ['type' => $type->label()]), ['consent_type' => $type->value]);
        }
    }

    /**
     * @return Builder<PatientConsent>
     */
    private function consents(): Builder
    {
        return OwnershipScope::apply(PatientConsent::query()->with(self::RELATIONS), 'consents', Auth::user());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assertScopeBelongsTo(Patient $patient, array $values): void
    {
        $owners = ['appointment_id' => Appointment::class, 'treatment_id' => Treatment::class, 'clinical_order_id' => ClinicalOrder::class];
        foreach ($owners as $field => $model) {
            if (($values[$field] ?? null) !== null && ! $model::query()->whereKey($values[$field])->where('patient_id', $patient->id)->exists()) {
                throw ValidationException::withMessages([$field => [__('validation.exists', ['attribute' => str_replace('_', ' ', $field)])]]);
            }
        }
    }
}
