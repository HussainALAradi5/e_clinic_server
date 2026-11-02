<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Image format of generated codes: QR for documents / cards, Code-128 for wristbands and labels, DataMatrix for small labels.
 */
enum CodeSymbology: string implements HasLabel
{
    use EnumHelpers;

    case Qr = 'qr';
    case Code128 = 'code128';
    case Datamatrix = 'datamatrix';
}
