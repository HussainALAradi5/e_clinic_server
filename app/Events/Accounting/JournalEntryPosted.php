<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

final class JournalEntryPosted
{
    use Dispatchable;

    public function __construct(public readonly JournalEntry $entry) {}
}
