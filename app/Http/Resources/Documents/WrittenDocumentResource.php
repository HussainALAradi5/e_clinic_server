<?php

declare(strict_types=1);

namespace App\Http\Resources\Documents;

use App\Models\Documents\WrittenDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document written at a visit, with the patient's and author's names; open its PDF at
 * `documents/written_document/{id}/file`.
 *
 * @mixin WrittenDocument
 */
final class WrittenDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind->value,
            'title' => $this->title,
            'body' => $this->body,
            'appointment_id' => $this->appointment_id,
            'template_id' => $this->template_id,
            'patient' => ['id' => $this->patient_id, 'full_name' => $this->patient->person->full_name, 'code' => $this->patient->person->code],
            'author' => ['id' => $this->author_id, 'full_name' => $this->author->person->full_name, 'job_title' => $this->author->job_title],
            'issued_at' => $this->issued_at->toIso8601String(),
        ];
    }
}
