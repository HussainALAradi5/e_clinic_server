<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Enums\Payments\ServiceCategory;
use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Diagnostic order or referral (one model for imaging, lab tests, ECG and referrals).
 */
enum ClinicalOrderType: string implements HasLabel
{
    use EnumHelpers;

    case Xray = 'xray';
    case Ultrasound = 'ultrasound';
    case Ct = 'ct';
    case Mri = 'mri';
    case LabTest = 'lab_test';
    case Ecg = 'ecg';
    case Referral = 'referral';

    /** Imaging results need at least one image attachment. */
    public function isImaging(): bool
    {
        return in_array($this, [self::Xray, self::Ultrasound, self::Ct, self::Mri], true);
    }

    /** Room type an order is performed in (referrals have none). */
    public function roomType(): ?RoomType
    {
        return match ($this) {
            self::Xray, self::Ultrasound, self::Ct, self::Mri => RoomType::Xray,
            self::LabTest => RoomType::Laboratory,
            self::Ecg => RoomType::TreatmentRoom,
            self::Referral => null,
        };
    }

    /** Service category billed for this order type. */
    public function serviceCategory(): ?ServiceCategory
    {
        return match ($this) {
            self::Xray, self::Ultrasound, self::Ct, self::Mri, self::Ecg => ServiceCategory::Radiology,
            self::LabTest => ServiceCategory::Laboratory,
            self::Referral => null,
        };
    }
}
