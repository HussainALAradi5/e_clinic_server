<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Images of the clinic profile printed on PDFs: the letterhead logo and the stamp beside signatures.
 */
enum ClinicImage: string implements HasLabel
{
    use EnumHelpers;

    case Logo = 'logo';
    case Stamp = 'stamp';

    /** clinic_profiles column holding the attachment. */
    public function column(): string
    {
        return $this->value.'_attachment_id';
    }
}
