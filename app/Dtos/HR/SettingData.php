<?php

declare(strict_types=1);

namespace App\Dtos\HR;

/** Input of an HR setting. `bands` null leaves the overtime time bands as they are; an empty list removes them. */
final readonly class SettingData
{
    /**
     * @param  list<OvertimeBandData>|null  $bands
     */
    public function __construct(
        public bool $enabled,
        public ?string $amount = null,
        public ?array $bands = null,
    ) {}
}
