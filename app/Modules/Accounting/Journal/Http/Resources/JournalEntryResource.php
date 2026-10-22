<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Http\Resources;

use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Accounting\Journal\Models\JournalEntryLine;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Journal entry; lines, period and reversal links when loaded (detail).
 *
 * @mixin JournalEntry
 */
final class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date->toDateString(),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'is_manual' => $this->isManual(),
            'description' => $this->description,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'total_debit' => MoneyHelper::toApi(MoneyHelper::of($this->total_debit)),
            'total_credit' => MoneyHelper::toApi(MoneyHelper::of($this->total_credit)),
            'fiscal_period' => $this->whenLoaded('fiscalPeriod', fn (): array => [
                'id' => $this->fiscalPeriod->id,
                'name' => $this->fiscalPeriod->name,
                'status' => $this->fiscalPeriod->status->value,
            ]),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (JournalEntryLine $l): array => [
                'id' => $l->id,
                'line_number' => $l->line_number,
                'account_id' => $l->account_id,
                'account' => $l->relationLoaded('account') ? ['id' => $l->account->id, 'code' => $l->account->code, 'name' => $l->account->name] : null,
                'debit' => MoneyHelper::toApi(MoneyHelper::of($l->debit)),
                'credit' => MoneyHelper::toApi(MoneyHelper::of($l->credit)),
                'description' => $l->description,
                'department_id' => $l->department_id,
                'department' => $l->relationLoaded('department') && $l->department !== null ? ['id' => $l->department->id, 'name' => $l->department->name] : null,
                'patient_id' => $l->patient_id,
                'patient' => $l->relationLoaded('patient') && $l->patient !== null ? ['id' => $l->patient->id, 'full_name' => $l->patient->person->full_name] : null,
                'doctor_id' => $l->doctor_id,
                'doctor' => $l->relationLoaded('doctor') && $l->doctor !== null ? ['id' => $l->doctor->id, 'full_name' => $l->doctor->person->full_name] : null,
            ])->all()),
            'reversal_of' => $this->whenLoaded('reversalOf', fn (): ?array => $this->reversalOf === null ? null : ['id' => $this->reversalOf->id, 'entry_number' => $this->reversalOf->entry_number]),
            'reversed_by' => $this->whenLoaded('reversedBy', fn (): ?array => $this->reversedBy === null ? null : ['id' => $this->reversedBy->id, 'entry_number' => $this->reversedBy->entry_number]),
            'posted_by' => $this->whenLoaded('poster', fn (): ?array => $this->poster === null ? null : ['id' => $this->poster->id, 'full_name' => $this->poster->person->full_name]),
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : ['id' => $this->creator->id, 'full_name' => $this->creator->person->full_name]),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
