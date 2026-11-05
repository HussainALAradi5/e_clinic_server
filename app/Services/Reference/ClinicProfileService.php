<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Dtos\Reference\ClinicProfileData;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Reference\ClinicImage;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Models\Reference\ClinicProfile;
use App\Services\Shared\FileStorage;
use App\Utilities\Shared\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The clinic's name, tagline, contacts, logo and stamp, printed on every generated PDF. One row, created from
 * config/documents.php the first time it is read; the images are attachments (category `clinic_logo`).
 */
final class ClinicProfileService implements ClinicProfileServiceInterface
{
    public function __construct(
        private readonly FileStorage $files,
        private readonly StoredImage $images,
    ) {}

    public function current(): ClinicProfile
    {
        return ClinicProfile::query()->with(['logo', 'stamp'])->where('is_deleted', false)->oldest('created_at')->first()
            ?? ClinicProfile::query()->create(Config::array('documents.clinic'));
    }

    public function update(ClinicProfileData $data): ClinicProfile
    {
        $profile = $this->current();
        $profile->update($data->attributes());

        return $profile->refresh();
    }

    public function setImage(ClinicImage $image, UploadedFile $file): ClinicProfile
    {
        $profile = $this->current();

        return DB::transaction(function () use ($profile, $image, $file): ClinicProfile {
            $stored = $this->images->store($file, 'clinic_profile', $profile->id, AttachmentCategory::ClinicLogo, 'branding');
            $profile->{$image->value}?->delete();
            $profile->update([$image->column() => $stored->id]);

            return $profile->refresh()->load(['logo', 'stamp']);
        });
    }

    public function removeImage(ClinicImage $image): ClinicProfile
    {
        $profile = $this->current();
        $profile->{$image->value}?->delete();
        $profile->update([$image->column() => null]);

        return $profile->refresh();
    }

    public function image(ClinicImage $image): ?array
    {
        $attachment = $this->current()->{$image->value};
        $bytes = $attachment !== null ? $this->files->disk()->get($attachment->path) : null;

        return $bytes === null ? null : ['bytes' => $bytes, 'mime' => $attachment->mime_type];
    }
}
