<?php

declare(strict_types=1);

namespace App\Interfaces\Reference;

use App\Dtos\Payments\OnlinePaymentSettings;
use App\Dtos\Reference\ClinicProfileData;
use App\Dtos\Reference\OnlinePaymentSettingsData;
use App\Enums\Reference\ClinicImage;
use App\Models\Reference\ClinicProfile;
use Illuminate\Http\UploadedFile;

interface ClinicProfileServiceInterface
{
    /** The clinic profile (made from config/documents.php the first time). */
    public function current(): ClinicProfile;

    public function update(ClinicProfileData $data): ClinicProfile;

    /** Replaces the logo or the stamp (PNG or JPEG, up to 1 MB). */
    public function setImage(ClinicImage $image, UploadedFile $file): ClinicProfile;

    /** Removes the logo (back to the e-Clinic mark) or the stamp (an empty stamp box). */
    public function removeImage(ClinicImage $image): ClinicProfile;

    /**
     * The image file, or null when none is set.
     *
     * @return array{bytes: string, mime: string}|null
     */
    public function image(ClinicImage $image): ?array;

    /** Online payments as they apply now: the switch, Tap settings (saved first, then `.env`) and Tap's addresses. */
    public function onlinePayments(): OnlinePaymentSettings;

    /** Switches online payments on or off and saves the Tap settings; a null secret key keeps the saved one. */
    public function updateOnlinePayments(OnlinePaymentSettingsData $data): OnlinePaymentSettings;
}
