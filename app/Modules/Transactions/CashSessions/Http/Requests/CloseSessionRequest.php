<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Http\Requests;

use App\Modules\Transactions\CashSessions\Data\CloseSessionData;
use App\Shared\Support\MoneyHelper;
use Illuminate\Foundation\Http\FormRequest;

/**
 * End-of-shift count: the counted cash, optionally broken down by note / coin (`[{value, count}]`).
 */
final class CloseSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_closing' => ['required', 'decimal:0,3', 'min:0'],
            'denominations' => ['nullable', 'list', 'max:20'],
            'denominations.*.value' => ['required', 'decimal:0,3', 'gt:0'],
            'denominations.*.count' => ['required', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): CloseSessionData
    {
        /** @var ?list<array{value: string, count: int}> $denominations */
        $denominations = $this->validated('denominations');
        $counts = [];
        foreach ($denominations ?? [] as $row) {
            if ((int) $row['count'] > 0) {
                $counts[MoneyHelper::toDb(MoneyHelper::of((string) $row['value']))] = (int) $row['count'];
            }
        }

        return new CloseSessionData(
            (string) $this->validated('counted_closing'),
            $denominations === null ? null : $counts,
            $this->filled('notes') ? $this->string('notes')->toString() : null,
        );
    }
}
