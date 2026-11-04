<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Logical accounts used by automatic postings; mapped to real accounts in account_mappings.
 */
enum AccountMappingKey: string implements HasLabel
{
    use EnumHelpers;

    case CashOnHand = 'cash_on_hand';
    case Bank = 'bank';
    case CardClearing = 'card_clearing';
    case AccountsReceivable = 'accounts_receivable';
    case InsuranceReceivable = 'insurance_receivable';
    case InputVat = 'input_vat';
    case VatPayable = 'vat_payable';
    case SalariesPayable = 'salaries_payable';
    case SocialInsurancePayable = 'social_insurance_payable';
    case SalariesExpense = 'salaries_expense';
    case SalesReturns = 'sales_returns';
    case CashOverShort = 'cash_over_short';
    case RetainedEarnings = 'retained_earnings';
    case PharmacyInventory = 'pharmacy_inventory';
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case PatientDeposits = 'patient_deposits';
    case FeeRevenue = 'fee_revenue';

    /** Account type a mapped account must have. */
    public function accountType(): AccountType
    {
        return match ($this) {
            self::CashOnHand, self::Bank, self::CardClearing, self::AccountsReceivable, self::InsuranceReceivable, self::InputVat, self::PharmacyInventory => AccountType::Asset,
            self::VatPayable, self::SalariesPayable, self::SocialInsurancePayable, self::PatientDeposits => AccountType::Liability,
            self::SalariesExpense, self::CashOverShort, self::CostOfGoodsSold => AccountType::Expense,
            self::SalesReturns, self::FeeRevenue => AccountType::Revenue,
            self::RetainedEarnings => AccountType::Equity,
        };
    }
}
