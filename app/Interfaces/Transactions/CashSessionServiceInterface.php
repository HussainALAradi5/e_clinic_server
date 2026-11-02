<?php

declare(strict_types=1);

namespace App\Interfaces\Transactions;

use App\Dtos\Transactions\CashSessionReport;
use App\Dtos\Transactions\CloseSessionData;
use App\Dtos\Transactions\DepositData;
use App\Models\Transactions\CashSession;
use App\Utilities\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CashSessionServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, CashSession>|CursorPaginator<int, CashSession>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): CashSession;

    /** The user's open (or closing) session, or null. */
    public function current(Authenticatable $cashier): ?CashSession;

    /** The user's open session for a cash document; none → NoOpenCashSessionException. */
    public function requireOpen(Authenticatable $cashier): CashSession;

    public function open(Authenticatable $cashier, Money $openingFloat): CashSession;

    /** Counts and closes; a difference above the tolerance leaves it `closing` for a supervisor. */
    public function close(CashSession $session, CloseSessionData $data): CashSession;

    /** A supervisor (not the cashier) accepts the difference: over / short entry, then `closed`. */
    public function approveDifference(CashSession $session, string $notes): CashSession;

    /** Bank deposit of a closed session: Dr bank / Cr cash on hand. */
    public function deposit(CashSession $session, DepositData $data): CashSession;

    /** Z-report figures (expected cash computed from the documents). */
    public function report(CashSession $session): CashSessionReport;
}
