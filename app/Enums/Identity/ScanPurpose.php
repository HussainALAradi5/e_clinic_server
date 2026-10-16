<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

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
