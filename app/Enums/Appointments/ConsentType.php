<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * What the patient consents to (general_treatment once at registration, the others per procedure / order).
 */
enum ConsentType: string implements HasLabel
{
    use EnumHelpers;

    case GeneralTreatment = 'general_treatment';
    case Procedure = 'procedure';
    case Anesthesia = 'anesthesia';
    case ContrastMedia = 'contrast_media';
    case Telemedicine = 'telemedicine';
    case Photography = 'photography';
    case DataSharing = 'data_sharing';
}
