<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\Payments\DiscountType;
use App\Models\Payments\Invoice;
use App\Models\Payments\InvoiceItem;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Line and invoice arithmetic with brick/money (HALF_UP, 3 decimals): gross = qty × price, discount (fixed or %),
 * VAT on the discounted amount, line total = gross − discount + VAT.
 */
final class InvoiceCalculator
{
    /**
     * @return array{gross: Money, discount: Money, tax: Money, total: Money}
     */
    public function line(string $quantity, Money $unitPrice, ?DiscountType $discountType, string $discountValue, string $taxRate): array
    {
        $gross = $unitPrice->multipliedBy(BigDecimal::of($quantity), RoundingMode::HALF_UP);
        $discount = match ($discountType) {
            DiscountType::Fixed => MoneyHelper::of($discountValue, $unitPrice->getCurrency()->getCurrencyCode()),
            DiscountType::Percentage => MoneyHelper::percentage($gross, $discountValue),
            null => Money::zero($unitPrice->getCurrency()),
        };
        if ($discount->isGreaterThan($gross)) {
            $discount = $gross;
        }
        $tax = MoneyHelper::percentage($gross->minus($discount), $taxRate);

        return ['gross' => $gross, 'discount' => $discount, 'tax' => $tax, 'total' => $gross->minus($discount)->plus($tax)];
    }

    /** Discount as a percent of the line's gross amount (for the role limit). */
    public function discountPercent(Money $gross, Money $discount): BigDecimal
    {
        return $gross->isZero() ? BigDecimal::zero() : $discount->getAmount()->multipliedBy(100)->dividedBy($gross->getAmount(), 2, RoundingMode::UP);
    }

    /** Recomputes the header totals from the lines (balance stays 0 until the invoice is issued). */
    public function refresh(Invoice $invoice): void
    {
        $currency = $invoice->currency;
        $items = $invoice->items()->get();
        $sum = fn (callable $pick): Money => $items->reduce(fn (Money $total, InvoiceItem $i): Money => $total->plus($pick($i)), MoneyHelper::zero($currency));

        $subtotal = $sum(fn (InvoiceItem $i): Money => $i->unit_price->multipliedBy(BigDecimal::of($i->quantity), RoundingMode::HALF_UP));
        $discount = $sum(fn (InvoiceItem $i): Money => $i->discount_amount);
        $tax = $sum(fn (InvoiceItem $i): Money => $i->tax_amount);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'total' => $subtotal->minus($discount)->plus($tax),
        ]);
    }
}
