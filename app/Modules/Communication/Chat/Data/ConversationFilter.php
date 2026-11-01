<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Data;

use App\Enums\Communication\ConversationType;
use Carbon\CarbonImmutable;

/** Filters of the chat list (all optional): name, type, read state, the last-activity date range, deleted chats (admins). */
final readonly class ConversationFilter
{
    public function __construct(
        public ?string $search = null,
        public ?ConversationType $type = null,
        public ?bool $unread = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public int $perPage = 30,
        /** Admins only: every deleted chat instead of mine. */
        public bool $deleted = false,
    ) {}
}
