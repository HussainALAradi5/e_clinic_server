<?php

declare(strict_types=1);

namespace App\Http\Resources\Transactions;

use App\Dtos\Transactions\CashSessionReport;
use App\Http\Resources\Shared\Summary;
use App\Models\Transactions\CashSession;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cash drawer shift; `report` (expected cash and how it is built, Z-report takings) on the desk and detail.
 *
 * @mixin CashSession
 */
final class CashSessionResource extends JsonResource
{
    private ?CashSessionReport $report = null;

    public function withReport(CashSessionReport $report): self
    {
        $this->report = $report;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $money = fn (?Money $m): ?array => $m === null ? null : MoneyHelper::toApi($m);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'cashier_id' => $this->cashier_id,
            'cashier' => $this->whenLoaded('cashier', fn (): ?array => Summary::user($this->cashier)),
            'opened_at' => $this->opened_at->toIso8601String(),
            'opening_float' => MoneyHelper::toApi($this->opening_float),
            'expected_closing' => $money($this->expected_closing),
            'counted_closing' => $money($this->counted_closing),
            'difference' => $money($this->difference),
            // A list, not a value-keyed map: numeric keys would not survive JSON resources.
            'denominations' => $this->denominations === null ? null : array_map(
                fn (string|int $value, int $count): array => ['value' => (string) $value, 'count' => $count],
                array_keys($this->denominations),
                array_values($this->denominations),
            ),
            'status' => $this->status->value,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'approved_by' => $this->whenLoaded('approver', fn (): ?array => Summary::user($this->approver)),
            'deposit_amount' => $money($this->deposit_amount),
            'deposited_at' => $this->deposited_at?->toDateString(),
            'deposit_reference' => $this->deposit_reference,
            'notes' => $this->notes,
            'report' => $this->when($this->report !== null, fn (): array => self::report($this->report)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function report(?CashSessionReport $r): array
    {
        if ($r === null) {
            return [];
        }

        return [
            'opening_float' => MoneyHelper::toApi($r->openingFloat),
            'cash_in' => MoneyHelper::toApi($r->cashIn),
            'cash_refunds' => MoneyHelper::toApi($r->cashRefunds),
            'cash_expenses' => MoneyHelper::toApi($r->cashExpenses),
            'balance_deposits' => MoneyHelper::toApi($r->balanceDeposits),
            'balance_paybacks' => MoneyHelper::toApi($r->balancePaybacks),
            'expected' => MoneyHelper::toApi($r->expected),
            // Lists, not enum-keyed maps: the client camelCases object keys.
            'by_method' => array_map(fn (string $method, Money $total): array => ['method' => $method, 'total' => MoneyHelper::toApi($total)], array_keys($r->byMethod), array_values($r->byMethod)),
            'payments' => $r->payments,
            'refunds' => $r->refunds,
            'expenses' => $r->expenses,
        ];
    }
}
