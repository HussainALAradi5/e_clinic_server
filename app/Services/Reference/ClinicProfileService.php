<?php

declare(strict_types=1);

namespace App\Services\Reference;

use App\Dtos\Payments\OnlinePaymentSettings;
use App\Dtos\Reference\ClinicProfileData;
use App\Dtos\Reference\OnlinePaymentSettingsData;
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
            // Read back once created, so the column defaults (online payments off) are loaded too.
            ?? ClinicProfile::query()->create(Config::array('documents.clinic'))->refresh();
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

    public function onlinePayments(): OnlinePaymentSettings
    {
        $profile = $this->current();
        $secret = $profile->tap_secret_key ?? (string) Config::get('services.tap.secret_key');

        return new OnlinePaymentSettings(
            enabled: $profile->online_payments_enabled,
            driver: $secret !== '' ? 'tap' : Config::string('payments.online.driver'),
            baseUrl: $profile->tap_base_url ?? Config::string('services.tap.base_url'),
            source: $profile->tap_source ?? Config::string('services.tap.source'),
            secretKey: $secret !== '' ? $secret : null,
            webhookUrl: route('webhooks.tap'),
            returnUrl: route('online-payments.return'),
        );
    }

    public function updateOnlinePayments(OnlinePaymentSettingsData $data): OnlinePaymentSettings
    {
        $profile = $this->current();
        $profile->online_payments_enabled = $data->enabled;
        $profile->tap_base_url = $data->baseUrl;
        $profile->tap_source = $data->source;
        if ($data->clearSecretKey) {
            $profile->tap_secret_key = null;
        } elseif ($data->secretKey !== null) {
            $profile->tap_secret_key = $data->secretKey;
        }
        $profile->save();

        return $this->onlinePayments();
    }
}
