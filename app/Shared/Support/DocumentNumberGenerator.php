<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gap-free document numbers from `document_sequences`: yearly-reset (`INV-2026-000123`) for documents,
 * or one running sequence (`PRS-000123`, stored with year 0) for master data such as persons and roles.
 *
 * Must run inside the caller's DB::transaction(): the sequence row is locked FOR UPDATE, so a rollback leaves no gap.
 */
class DocumentNumberGenerator
{
    public const TABLE = 'document_sequences';

    public function __construct(private readonly ClinicClock $clock) {}

    public function next(string $documentType, string $prefix, ?CarbonInterface $date = null, int $pad = 6, bool $yearly = true): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('DocumentNumberGenerator::next() must be called inside a database transaction.');
        }

        $year = $yearly ? ($date ?? $this->clock->today())->year : 0;
        $key = ['document_type' => $documentType, 'year' => $year];

        DB::table(self::TABLE)->insertOrIgnore([...$key, 'prefix' => $prefix, 'last_number' => 0, 'updated_at' => $this->clock->now()]);

        $current = (int) DB::table(self::TABLE)->where($key)->lockForUpdate()->value('last_number');
        $number = $current + 1;

        DB::table(self::TABLE)->where($key)->update(['last_number' => $number, 'updated_at' => $this->clock->now()]);

        $sequence = str_pad((string) $number, $pad, '0', STR_PAD_LEFT);

        return $yearly ? sprintf('%s-%d-%s', $prefix, $year, $sequence) : "{$prefix}-{$sequence}";
    }
}
