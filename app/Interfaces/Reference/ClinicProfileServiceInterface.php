<?php

declare(strict_types=1);

namespace App\Interfaces\Reference;

use App\Dtos\Reference\ClinicProfileData;
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
}
