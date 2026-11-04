<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Dtos\Shared\ChargeLine;
use App\Enums\Documents\WrittenDocumentKind;
use App\Interfaces\Shared\Chargeable;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\Base\BaseModel;
use App\Models\HR\Employee;
use App\Models\Payments\BillableService;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * A document written at a visit (WRD-2026-000001) — medical report, attendance or fitness certificate, referral note,
 * letter — from a template or from scratch, in rich text; printed as a branded PDF with the author's signature and the
 * clinic stamp. The body is PHI (encrypted). Seen by the patient, the author and the front desk.
 *
 * @property string $id
 * @property string $code
 * @property string $appointment_id
 * @property string $patient_id
 * @property string $author_id
 * @property ?string $template_id
 * @property WrittenDocumentKind $kind
 * @property string $title
 * @property string $body
 * @property CarbonImmutable $issued_at
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Appointment $appointment
 * @property-read Patient $patient
 * @property-read Employee $author
 * @property-read ?DocumentTemplate $template
 */
class WrittenDocument extends BaseModel implements Chargeable, OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'WRD';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('written_documents')
            ->sortable('issued_at', 'code', 'kind', 'title')
            ->filterable('kind', ['eq', 'in'], enum: WrittenDocumentKind::class)
            ->filterable('patient_id', ['eq'])
            ->filterable('appointment_id', ['eq'])
            ->searchIn('title', 'code')
            ->includes('patient.person', 'author.person')
            ->column('code', 'Code')
            ->column('title', 'Title')
            ->column('kind', 'Kind')
            ->column('issued_at', 'Issued')
            ->dateFields('issued_at')
            ->defaultSort('-issued_at');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->author_id], true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        $id = $user->getAuthIdentifier();

        return $query->where(fn (Builder $q) => $q->where('written_documents.patient_id', $id)->orWhere('written_documents.author_id', $id));
    }

    protected function casts(): array
    {
        return [
            'kind' => WrittenDocumentKind::class,
            'body' => 'encrypted',
            'issued_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** The kind's price-list item when it costs money (config payments.fees.documents); none for free kinds. */
    public function chargeLines(): array
    {
        $code = Config::get('payments.fees.documents.'.$this->kind->value.'.code');
        $service = $code === null ? null : BillableService::query()->where('code', $code)->where('is_archived', false)->first();
        if ($service === null || ! $service->price->isPositive()) {
            return [];
        }

        return [new ChargeLine($service->id, '1', $service->name.' — '.$this->code)];
    }
}
