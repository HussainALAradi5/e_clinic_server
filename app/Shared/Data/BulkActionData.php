<?php

declare(strict_types=1);

namespace App\Shared\Data;

use App\Shared\Http\Requests\BulkActionRequest;

/**
 * Bulk request: target set is `ids` (≤ 500) or `filter` (select all matching, ListQuery grammar) — never both.
 */
final readonly class BulkActionData
{
    /**
     * @param  list<string>|null  $ids
     * @param  array<string, mixed>|null  $filter  snake_case ListQuery parameters
     */
    public function __construct(
        public string $action,
        public ?array $ids = null,
        public ?array $filter = null,
        public ?string $reason = null,
        public bool $dryRun = false,
    ) {}

    public static function fromRequest(BulkActionRequest $request): self
    {
        $data = $request->validated();

        return new self(
            action: $data['action'],
            ids: isset($data['ids']) ? array_values(array_unique($data['ids'])) : null,
            filter: $data['filter'] ?? null,
            reason: $data['reason'] ?? null,
            dryRun: (bool) ($data['dry_run'] ?? false),
        );
    }
}
