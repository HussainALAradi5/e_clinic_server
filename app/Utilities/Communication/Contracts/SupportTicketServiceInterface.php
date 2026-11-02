<?php

declare(strict_types=1);

namespace App\Utilities\Communication\Contracts;

use App\Enums\Shared\GenericStatus;
use App\Models\Identity\User;
use App\Utilities\Communication\Data\TicketData;
use App\Utilities\Communication\Models\SupportTicket;
use App\Utilities\Communication\Models\TicketComment;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Support tickets: staff open them and follow their own; admins (tickets.read_all + tickets.resolve) see all and
 * are the only ones who move the status. Every change is broadcast live and notified.
 */
interface SupportTicketServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, SupportTicket>|CursorPaginator<int, SupportTicket>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** A ticket the user may see (own, or all with tickets.read_all), with its comments. */
    public function find(string $id): SupportTicket;

    /** Opens a ticket for the user; the admins are told. */
    public function open(User $requester, TicketData $data): SupportTicket;

    /** A reply (never changes the status: a solved ticket is confirmed or declined explicitly). */
    public function comment(SupportTicket $ticket, User $author, string $body): TicketComment;

    /**
     * Admins only: pending ⇄ in progress → completed (solved) or cancelled; a solved ticket can be reopened. Taking a
     * ticket assigns it to the admin; marking it solved needs the solution in words (and optionally the steps) and asks
     * the requester to confirm.
     *
     * @param  list<string>  $steps  optional, in order
     */
    public function changeStatus(SupportTicket $ticket, User $admin, GenericStatus $to, ?string $resolution, array $steps, int $version): SupportTicket;

    /** The requester confirms a solved ticket works; whoever solved it is told. */
    public function confirm(SupportTicket $ticket, User $requester): SupportTicket;

    /** The requester says it is still broken: back to in progress with the reason; whoever solved it is told. */
    public function decline(SupportTicket $ticket, User $requester, string $reason): SupportTicket;
}
