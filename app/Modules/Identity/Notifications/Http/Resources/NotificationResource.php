<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Http\Resources;

use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\RoleName;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'template' => $this->template,
            'locale' => $this->locale,
            'destination' => $this->maskedDestination($request->user()),
            'subject' => $this->subject,
            'body' => $this->body,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'status' => $this->status->value,
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'person' => $this->whenLoaded('person', fn (): array => ['id' => $this->person->id, 'full_name' => $this->person->full_name]),
        ];
    }

    /** Destinations are masked for everyone but administrators and the recipient. */
    private function maskedDestination(mixed $viewer): ?string
    {
        $value = $this->destination;
        if ($value === null || $this->channel === NotificationChannel::Push
            || ($viewer instanceof User && ($this->isOwnedBy($viewer) || $viewer->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value])))) {
            return $value;
        }

        return $this->channel === NotificationChannel::Email
            ? Str::mask($value, '*', 1, max(0, (int) strpos($value, '@') - 1))
            : Str::mask($value, '*', 0, -4);
    }
}
