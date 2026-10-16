<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Data;

use App\Enums\Identity\DevicePlatform;
use App\Enums\Identity\MobileApp;

final readonly class DeviceData
{
    public function __construct(
        public string $token,
        public MobileApp $app,
        public DevicePlatform $platform,
        public ?string $deviceName = null,
        public ?string $appVersion = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            token: (string) $input['token'],
            app: MobileApp::from((string) $input['app']),
            platform: DevicePlatform::from((string) $input['platform']),
            deviceName: isset($input['device_name']) ? (string) $input['device_name'] : null,
            appVersion: isset($input['app_version']) ? (string) $input['app_version'] : null,
        );
    }
}
