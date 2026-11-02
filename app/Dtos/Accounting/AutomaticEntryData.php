<?php

declare(strict_types=1);

namespace App\Dtos\Accounting;

use App\Enums\Accounting\JournalEntryType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry posted in one step by a source document (invoice, payment, refund, expense, cash session, claim remittance).
 * Zero-amount lines are dropped; mapping keys are resolved to real accounts.
 */
final readonly class AutomaticEntryData
{
    /**
     * @param  list<JournalLineData>  $lines
     */
    public function __construct(
        public Model $source,
        public CarbonInterface $date,
        public string $description,
        public array $lines,
        public JournalEntryType $type = JournalEntryType::Automatic,
    ) {}
}
