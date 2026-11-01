<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Support;

use App\Modules\Identity\Users\Models\Person;
use App\Shared\Files\FileStorage;

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
