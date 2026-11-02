<?php

declare(strict_types=1);

namespace App\Enums\Transactions;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Operating expense category (picks the default expense account).
 */
enum ExpenseCategory: string implements HasLabel
{
    use EnumHelpers;

    case Rent = 'rent';
    case Utilities = 'utilities';
    case MedicalSupplies = 'medical_supplies';
    case OfficeSupplies = 'office_supplies';
    case Maintenance = 'maintenance';
    case Marketing = 'marketing';
    case Insurance = 'insurance';
    case GovernmentFees = 'government_fees';
    case ProfessionalFees = 'professional_fees';
    case PharmacyPurchase = 'pharmacy_purchase';
    case Other = 'other';
}
