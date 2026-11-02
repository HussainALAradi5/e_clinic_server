<?php

declare(strict_types=1);

namespace App\Utilities\Communication\Http\Controllers;

use App\Enums\Shared\GenericStatus;
use App\Http\Concerns\ApiResponse;
use App\Models\Identity\User;
use App\Utilities\Communication\Contracts\SupportTicketServiceInterface;
use App\Utilities\Communication\Http\Requests\TicketRequest;
use App\Utilities\Communication\Http\Resources\TicketCommentResource;
use App\Utilities\Communication\Http\Resources\TicketResource;
use App\Utilities\Communication\Models\SupportTicket;
use App\Utilities\Query\ListQuery;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Support tickets: open, follow, reply; admins move the status. */
final class SupportTicketController
{
    use ApiResponse;

    public function __construct(private readonly SupportTicketServiceInterface $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, SupportTicket::listQueryConfig());

        return $this->paginated(TicketResource::collection($this->tickets->list($query)), $query);
    }

    public function show(string $ticket): JsonResponse
    {
        return $this->ok(new TicketResource($this->tickets->find($ticket)));
    }

    public function store(TicketRequest $request): JsonResponse
    {
        return $this->created(new TicketResource($this->tickets->open($this->me($request), $request->toData())));
    }

    public function comment(Request $request, string $ticket): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $this->tickets->comment($this->tickets->find($ticket), $this->me($request), trim($request->string('body')->toString()));

        return $this->created(new TicketCommentResource($comment));
    }

    public function status(Request $request, string $ticket): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(GenericStatus::class)],
            'resolution' => ['nullable', 'string', 'max:1000'],
            'steps' => ['nullable', 'array', 'max:20'],
            'steps.*' => ['nullable', 'string', 'max:500'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->ok(new TicketResource($this->tickets->changeStatus(
            $this->tickets->find($ticket),
            $this->me($request),
            GenericStatus::from($request->string('status')->toString()),
            $request->filled('resolution') ? trim($request->string('resolution')->toString()) : null,
            array_values(array_filter(array_map(fn (mixed $s): string => trim((string) $s), (array) $request->input('steps', [])), fn (string $s): bool => $s !== '')),
            $request->integer('version'),
        )));
    }

    /** The requester: it works. */
    public function confirm(Request $request, string $ticket): JsonResponse
    {
        return $this->ok(new TicketResource($this->tickets->confirm($this->tickets->find($ticket), $this->me($request))));
    }

    /** The requester: still broken, and why. */
    public function decline(Request $request, string $ticket): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $this->ok(new TicketResource($this->tickets->decline($this->tickets->find($ticket), $this->me($request), trim($request->string('reason')->toString()))));
    }

    private function me(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
