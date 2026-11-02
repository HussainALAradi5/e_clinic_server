<?php

declare(strict_types=1);

namespace App\Dtos\Accounting;

use App\Enums\Accounting\JournalEntryType;
use Carbon\CarbonImmutable;

/**
 * Manual (or adjustment) journal entry typed by an accountant.
 */
final readonly class JournalEntryData
{
    /**
     * @param  list<JournalLineData>  $lines
     */
    public function __construct(
        public CarbonImmutable $entryDate,
        public JournalEntryType $type,
        public string $description,
        public array $lines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $data['lines'];

        return new self(
            CarbonImmutable::parse((string) $data['entry_date']),
            JournalEntryType::from((string) $data['type']),
            (string) $data['description'],
            array_map(JournalLineData::fromArray(...), $lines),
        );
    }
}
