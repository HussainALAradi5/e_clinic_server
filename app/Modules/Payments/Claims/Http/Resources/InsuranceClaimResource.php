<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Http\Resources;

use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Invoices\Http\Resources\InvoiceItemResource;
use App\Shared\Http\Resources\Summary;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Insurance claim: draft → pending (sent) → approved / rejected → remitted. `diagnoses` (ICD-10 of the visits)
 * only on the detail for claim staff — never for the patient.
 *
 * @mixin InsuranceClaim
 */
final class InsuranceClaimResource extends JsonResource
{
    /** @var ?list<array{code: string, title: string, is_primary: bool, visit: string}> */
    private ?array $diagnoses = null;

    /**
     * @param  list<array{code: string, title: string, is_primary: bool, visit: string}>  $diagnoses
     */
    public function withDiagnoses(array $diagnoses): self
    {
        $this->diagnoses = $diagnoses;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'code' => $this->invoice->code,
                'invoice_number' => $this->invoice->invoice_number,
                'issue_date' => $this->invoice->issue_date?->toDateString(),
                'total' => MoneyHelper::toApi($this->invoice->total),
                'balance_due' => MoneyHelper::toApi($this->invoice->balance_due),
                'items' => $this->when($this->invoice->relationLoaded('items'), fn () => InvoiceItemResource::collection($this->invoice->items)),
            ]),
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => Summary::patient($this->patient)),
            'insurer_id' => $this->insurer_id,
            'insurer' => $this->whenLoaded('insurer', fn (): array => ['id' => $this->insurer->id, 'name' => $this->insurer->name]),
            'policy_number' => $this->policy_number,
            'coverage_percent' => $this->coverage_percent,
            'pre_authorization_number' => $this->pre_authorization_number,
            'claimed_amount' => MoneyHelper::toApi($this->claimed_amount),
            'approved_amount' => $this->approved_amount === null ? null : MoneyHelper::toApi($this->approved_amount),
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'insurer_reference' => $this->insurer_reference,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'payment_id' => $this->payment_id,
            'remitted_at' => $this->remitted_at?->toDateString(),
            'remittance_reference' => $this->remittance_reference,
            'diagnoses' => $this->when($this->diagnoses !== null, fn (): array => $this->diagnoses ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
