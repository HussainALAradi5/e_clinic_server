<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * What a scanned code is used for.
 */
enum ScanPurpose: string implements HasLabel
{
    use EnumHelpers;

    case Identify = 'identify';
    case CheckIn = 'check_in';
    case VerifyDocument = 'verify_document';
    case SampleLabel = 'sample_label';
}
