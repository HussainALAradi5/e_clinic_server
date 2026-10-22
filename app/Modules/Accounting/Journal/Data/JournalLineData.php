<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Data;

use App\Enums\Accounting\AccountMappingKey;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use LogicException;

/**
 * One debit or credit line. The account is a real account id or a logical mapping key (automatic postings).
 */
final readonly class JournalLineData
{
    public BigDecimal $debit;

    public BigDecimal $credit;

    public function __construct(
        public string|AccountMappingKey $account,
        BigDecimal|string $debit = '0',
        BigDecimal|string $credit = '0',
        public ?string $description = null,
        public ?string $departmentId = null,
        public ?string $patientId = null,
        public ?string $doctorId = null,
    ) {
        $this->debit = BigDecimal::of($debit)->toScale(3, RoundingMode::HALF_UP);
        $this->credit = BigDecimal::of($credit)->toScale(3, RoundingMode::HALF_UP);
    }

    public static function debit(string|AccountMappingKey $account, Money|BigDecimal|string $amount, ?string $description = null, ?string $patientId = null, ?string $doctorId = null, ?string $departmentId = null): self
    {
        return new self($account, self::decimal($amount), '0', $description, $departmentId, $patientId, $doctorId);
    }

    public static function credit(string|AccountMappingKey $account, Money|BigDecimal|string $amount, ?string $description = null, ?string $patientId = null, ?string $doctorId = null, ?string $departmentId = null): self
    {
        return new self($account, '0', self::decimal($amount), $description, $departmentId, $patientId, $doctorId);
    }

    /**
     * @param  array<string, mixed>  $line  validated request line
     */
    public static function fromArray(array $line): self
    {
        return new self(
            (string) $line['account_id'],
            (string) ($line['debit'] ?? '0'),
            (string) ($line['credit'] ?? '0'),
            isset($line['description']) ? (string) $line['description'] : null,
            isset($line['department_id']) ? (string) $line['department_id'] : null,
            isset($line['patient_id']) ? (string) $line['patient_id'] : null,
            isset($line['doctor_id']) ? (string) $line['doctor_id'] : null,
        );
    }

    /** Same line with debit and credit swapped (reversals). */
    public function mirrored(string $accountId): self
    {
        return new self($accountId, $this->credit, $this->debit, $this->description, $this->departmentId, $this->patientId, $this->doctorId);
    }

    /** Real account id (after the journal resolved mapping keys). */
    public function accountId(): string
    {
        return $this->account instanceof AccountMappingKey ? throw new LogicException('Unresolved account mapping key.') : $this->account;
    }

    public function isEmpty(): bool
    {
        return $this->debit->isZero() && $this->credit->isZero();
    }

    /** Exactly one side positive. */
    public function isValid(): bool
    {
        return ! $this->debit->isNegative() && ! $this->credit->isNegative() && ($this->debit->isZero() xor $this->credit->isZero());
    }

    private static function decimal(Money|BigDecimal|string $amount): BigDecimal
    {
        return $amount instanceof Money ? $amount->getAmount() : BigDecimal::of($amount);
    }
}
