<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Events;

use App\Modules\Accounting\Journal\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

final class JournalEntryPosted
{
    use Dispatchable;

    public function __construct(public readonly JournalEntry $entry) {}
}
