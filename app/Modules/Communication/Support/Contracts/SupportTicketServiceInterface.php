<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Contracts;

use App\Enums\Communication\TicketStatus;
use App\Modules\Communication\Support\Data\TicketData;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Communication\Support\Models\TicketComment;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Query\ListQuery;
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

    /** A reply. When the requester answers a resolved ticket it opens again (the fix did not hold). */
    public function comment(SupportTicket $ticket, User $author, string $body): TicketComment;

    /**
     * Admins only: open ⇄ in progress → resolved (with what was done) → closed. Taking a ticket in progress assigns
     * it to the admin; the requester is told of every change.
     */
    public function changeStatus(SupportTicket $ticket, User $admin, TicketStatus $to, ?string $resolution, int $version): SupportTicket;
}
