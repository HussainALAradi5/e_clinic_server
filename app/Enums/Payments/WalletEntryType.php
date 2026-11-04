<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * A movement of a patient's balance (money the clinic holds for them). Each one is posted to the ledger against the
 * Patient deposits account.
 */
enum WalletEntryType: string implements HasLabel
{
    use EnumHelpers;

    /** Money paid in at the desk (cash, card, transfer…) or online. */
    case Deposit = 'deposit';

    /** An invoice paid from the balance. */
    case InvoicePayment = 'invoice_payment';

    /** A balance payment voided the same day: the money comes back. */
    case PaymentReversal = 'payment_reversal';

    /** Money handed back to the patient (accountant). */
    case Withdrawal = 'withdrawal';

    /** A correction or goodwill credit (accountant / admin, with a reason). */
    case AdjustmentCredit = 'adjustment_credit';

    case AdjustmentDebit = 'adjustment_debit';

    /** A no-show or late-cancel fee taken from the balance. */
    case Fee = 'fee';

    /** True when the movement adds to the balance. */
    public function isCredit(): bool
    {
        return in_array($this, [self::Deposit, self::PaymentReversal, self::AdjustmentCredit], true);
    }
}
