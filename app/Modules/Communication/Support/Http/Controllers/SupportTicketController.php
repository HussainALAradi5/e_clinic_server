<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Http\Controllers;

use App\Enums\Communication\TicketStatus;
use App\Modules\Communication\Support\Contracts\SupportTicketServiceInterface;
use App\Modules\Communication\Support\Http\Requests\TicketRequest;
use App\Modules\Communication\Support\Http\Resources\TicketCommentResource;
use App\Modules\Communication\Support\Http\Resources\TicketResource;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'resolution' => ['nullable', 'string', 'max:1000'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->ok(new TicketResource($this->tickets->changeStatus(
            $this->tickets->find($ticket),
            $this->me($request),
            TicketStatus::from($request->string('status')->toString()),
            $request->filled('resolution') ? trim($request->string('resolution')->toString()) : null,
            $request->integer('version'),
        )));
    }

    private function me(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
