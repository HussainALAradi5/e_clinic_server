<?php

declare(strict_types=1);

namespace App\Modules\Communication;

use App\Events\Communication\TicketChanged;
use App\Http\Resources\Communication\TicketResource;
use App\Interfaces\Communication\ChatServiceInterface;
use App\Interfaces\Communication\SupportTicketServiceInterface;
use App\Listeners\Communication\SendTicketNotifications;
use App\Models\Communication\SupportTicket;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Communication\ChatService;
use App\Services\Communication\SupportTicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Communication module: staff chat (one-to-one and groups) and support tickets, both live over the socket (Reverb).
 */
final class CommunicationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        ChatServiceInterface::class => ChatService::class,
        SupportTicketServiceInterface::class => SupportTicketService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen(TicketChanged::class, SendTicketNotifications::class);

        // The ticket list (GET /meta/resources/tickets): filters, columns and export. No bulk actions — status
        // changes go one by one through the admins' flow.
        $bulk->register('tickets', SupportTicket::class, SupportTicket::listQueryConfig(), genericActions: false)
            ->exportable(TicketResource::class, fn (Builder $q) => $q->with(SupportTicketService::RELATIONS), values: [
                'requester' => fn (Model $t): string => $t instanceof SupportTicket ? $t->requester->person->full_name : '',
            ]);
    }
}
