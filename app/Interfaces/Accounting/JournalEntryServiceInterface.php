<?php

declare(strict_types=1);

namespace App\Interfaces\Accounting;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalEntryData;
use App\Models\Accounting\JournalEntry;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface JournalEntryServiceInterface
{
    /**
     * Entries by date, type, status, source, number; `$accountId` keeps entries with a line on that account.
     *
     * @return LengthAwarePaginator<int, JournalEntry>|CursorPaginator<int, JournalEntry>
     */
    public function list(ListQuery $query, ?string $accountId = null): LengthAwarePaginator|CursorPaginator;

    /** Entry with lines, period, reversal links and source. */
    public function find(string $id): JournalEntry;

    public function findWithDeleted(string $id): JournalEntry;

    /** Manual / adjustment draft; may be unbalanced until posted. */
    public function createDraft(JournalEntryData $data): JournalEntry;

    /** Replaces date, description and lines of a draft manual entry. */
    public function updateDraft(JournalEntry $entry, JournalEntryData $data): JournalEntry;

    /** Soft-deletes a draft manual entry. */
    public function deleteDraft(JournalEntry $entry): void;

    public function restoreDraft(JournalEntry $entry): JournalEntry;

    /** Posts a balanced draft into an open period (second person above the self-post limit). */
    public function post(JournalEntry $entry): JournalEntry;

    /** Internal API of source documents: creates and posts in one step. */
    public function postAutomatic(AutomaticEntryData $data): JournalEntry;

    /** Posts the mirror entry and marks the original reversed; automatic entries only from their source. */
    public function reverse(JournalEntry $entry, CarbonInterface $date, string $reason, bool $fromSource = false): JournalEntry;

    /** Posted (not reversed) entry of a source document, if any. */
    public function entryOf(Model $source): ?JournalEntry;
}
