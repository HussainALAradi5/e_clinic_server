<?php

declare(strict_types=1);

namespace App\Utilities\Identity;

use App\Models\Identity\Person;
use App\Services\Shared\FileStorage;

/** The signed, short-lived link to a person's profile photo (null when they have none). */
final class Avatar
{
    private const MINUTES = 120;

    public static function url(?Person $person): ?string
    {
        $path = $person?->photo_path;

        return $path === null ? null : app(FileStorage::class)->temporaryUrl($path, 'photo', self::MINUTES, inline: true)['url'];
    }
}
