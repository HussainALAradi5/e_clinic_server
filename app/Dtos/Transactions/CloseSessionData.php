<?php

declare(strict_types=1);

namespace App\Dtos\Transactions;

final readonly class CloseSessionData
{
    /**
     * @param  ?array<string, int>  $denominations  note / coin value => count
     */
    public function __construct(
        public string $countedClosing,
        public ?array $denominations = null,
        public ?string $notes = null,
    ) {}
}
