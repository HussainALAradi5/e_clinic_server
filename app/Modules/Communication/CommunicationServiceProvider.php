<?php

declare(strict_types=1);

namespace App\Modules\Communication;

use App\Modules\Communication\Chat\Contracts\ChatServiceInterface;
use App\Modules\Communication\Chat\Services\ChatService;
use App\Modules\Communication\Support\Contracts\SupportTicketServiceInterface;
use App\Modules\Communication\Support\Events\TicketChanged;
use App\Modules\Communication\Support\Http\Resources\TicketResource;
use App\Modules\Communication\Support\Listeners\SendTicketNotifications;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Communication\Support\Services\SupportTicketService;
use App\Shared\Bulk\BulkActionRegistry;
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
