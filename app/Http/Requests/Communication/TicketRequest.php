<?php

declare(strict_types=1);

namespace App\Http\Requests\Communication;

use App\Dtos\Communication\TicketData;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Communication\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:4', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['sometimes', Rule::enum(TreatmentPriority::class)],
        ];
    }

    public function toData(): TicketData
    {
        return new TicketData(
            trim($this->string('subject')->toString()),
            trim($this->string('description')->toString()),
            TicketCategory::from($this->string('category')->toString()),
            $this->filled('priority') ? TreatmentPriority::from($this->string('priority')->toString()) : TreatmentPriority::Medium,
        );
    }
}
