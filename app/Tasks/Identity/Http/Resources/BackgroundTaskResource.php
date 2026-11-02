<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Http\Resources;

use App\Enums\Shared\GenericStatus;
use App\Tasks\Identity\Models\BackgroundTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BackgroundTask
 */
final class BackgroundTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'progress' => $this->progress,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'result_summary' => $this->result_summary,
            'has_result' => $this->status === GenericStatus::Completed && $this->result_path !== null
                && ($this->expires_at === null || $this->expires_at->isFuture()),
            'cancel_reason' => $this->cancel_reason?->value,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'requested_by' => $this->whenLoaded('requester', fn (): ?array => $this->requester === null ? null : [
                'id' => $this->requester->id,
                'full_name' => $this->requester->person->full_name,
            ]),
        ];
    }
}
