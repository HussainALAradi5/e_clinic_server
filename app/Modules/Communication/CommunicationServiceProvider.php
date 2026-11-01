<?php

declare(strict_types=1);

namespace App\Modules\Communication;

use App\Modules\Communication\Chat\Contracts\ChatServiceInterface;
use App\Modules\Communication\Chat\Services\ChatService;
use App\Modules\Communication\Support\Contracts\SupportTicketServiceInterface;
use App\Modules\Communication\Support\Events\TicketChanged;
use App\Modules\Communication\Support\Listeners\SendTicketNotifications;
use App\Modules\Communication\Support\Services\SupportTicketService;
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

    public function boot(): void
    {
        Event::listen(TicketChanged::class, SendTicketNotifications::class);
    }
}
