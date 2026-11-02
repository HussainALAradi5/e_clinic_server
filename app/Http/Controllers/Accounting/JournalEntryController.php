<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Resources\Accounting\JournalEntryResource;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class JournalEntryController
{
    use ApiResponse;

    public function __construct(private readonly JournalEntryServiceInterface $journal) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['account_id' => ['nullable', 'uuid']]);
        $query = ListQuery::fromRequest($request, JournalEntry::listQueryConfig());
        $account = $request->filled('account_id') ? $request->string('account_id')->toString() : null;

        return $this->paginated(JournalEntryResource::collection($this->journal->list($query, $account)), $query);
    }

    public function show(string $journalEntry): JsonResponse
    {
        return $this->ok(new JournalEntryResource($this->journal->find($journalEntry)));
    }

    public function store(JournalEntryRequest $request): JsonResponse
    {
        return $this->created(new JournalEntryResource($this->journal->createDraft($request->toData())));
    }

    public function update(JournalEntryRequest $request, string $journalEntry): JsonResponse
    {
        return $this->ok(new JournalEntryResource($this->journal->updateDraft($this->journal->find($journalEntry), $request->toData())));
    }

    public function destroy(string $journalEntry): Response
    {
        $this->journal->deleteDraft($this->journal->find($journalEntry));

        return $this->noContent();
    }

    public function restore(string $journalEntry): JsonResponse
    {
        return $this->ok(new JournalEntryResource($this->journal->restoreDraft($this->journal->findWithDeleted($journalEntry))));
    }

    public function post(string $journalEntry): JsonResponse
    {
        return $this->ok(new JournalEntryResource($this->journal->post($this->journal->find($journalEntry))));
    }

    public function reverse(Request $request, string $journalEntry): JsonResponse
    {
        $request->validate(['date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new JournalEntryResource($this->journal->reverse(
            $this->journal->find($journalEntry),
            CarbonImmutable::parse($request->string('date')->toString()),
            $request->string('reason')->toString(),
        )));
    }
}
