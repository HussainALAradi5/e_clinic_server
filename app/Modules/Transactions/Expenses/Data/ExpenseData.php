<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Data;

/**
 * Validated expense form values (column => value). Vendor-invoice scans are attachments uploaded on the expense.
 */
final readonly class ExpenseData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        unset($data['version']);

        return new self($data);
    }
}
