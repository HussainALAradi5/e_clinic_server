<?php

declare(strict_types=1);

namespace App\Dtos\Transactions;

use Brick\Money\Money;

/**
 * Z-report of a cash session: expected cash and how it was built, plus non-cash takings of the cashier.
 */
final readonly class CashSessionReport
{
    /**
     * @param  Money  $balanceDeposits  cash patients left on their balance during the session
     * @param  Money  $balancePaybacks  cash paid back from patient balances during the session
     * @param  array<string, Money>  $byMethod  completed payments taken by the cashier during the session, per method
     */
    public function __construct(
        public Money $openingFloat,
        public Money $cashIn,
        public Money $cashRefunds,
        public Money $cashExpenses,
        public Money $balanceDeposits,
        public Money $balancePaybacks,
        public Money $expected,
        public array $byMethod,
        public int $payments,
        public int $refunds,
        public int $expenses,
    ) {}
}
