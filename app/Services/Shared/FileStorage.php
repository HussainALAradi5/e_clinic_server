<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Private file storage (attachments, task results). Files get random UUID paths and are only reachable through
 * short-lived signed links (`GET /api/v1/files?t=…`), so no URL ever exposes a storage path or works for long.
 */
final class FileStorage
{
    public const ROUTE = 'files.show';

    public function __construct(private readonly ClinicClock $clock) {}

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return Config::string('clinic.files.disk', 'local');
    }

    /** Stores an upload under `<folder>/<yyyy>/<mm>/<uuid>.<ext>` and returns the path. */
    public function storePrivate(UploadedFile $file, string $folder): string
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin');

        return (string) $this->disk()->putFileAs($this->folder($folder), $file, Str::uuid7()->toString().'.'.$extension);
    }

    /** A fresh random path for a file written by the application (exports, ZIPs); its folder is created. */
    public function newPath(string $folder, string $extension): string
    {
        $directory = $this->folder($folder);
        $this->disk()->makeDirectory($directory);

        return $directory.'/'.Str::uuid7()->toString().'.'.$extension;
    }

    /** Absolute local path (local disks only) — for streaming writers such as ZipArchive / fputcsv. */
    public function absolutePath(string $path): string
    {
        return $this->disk()->path($path);
    }

    /**
     * Signed download link valid for `$minutes` (default clinic.files.link_minutes).
     *
     * @return array{url: string, expires_at: string}
     */
    public function temporaryUrl(string $path, string $downloadName, ?int $minutes = null, bool $inline = false): array
    {
        $expires = $this->clock->now()->addMinutes($minutes ?? Config::integer('clinic.files.link_minutes', 5));
        $token = Crypt::encryptString((string) json_encode(['disk' => $this->diskName(), 'path' => $path, 'name' => $downloadName, 'inline' => $inline]));

        return [
            'url' => URL::temporarySignedRoute(self::ROUTE, $expires, ['t' => $token]),
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /**
     * Decodes a link token (the route signature was already verified).
     *
     * @return array{disk: string, path: string, name: string, inline: bool}
     */
    public static function decodeToken(string $token): array
    {
        /** @var array{disk: string, path: string, name: string, inline?: bool} $data */
        $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);

        return [...$data, 'inline' => (bool) ($data['inline'] ?? false)];
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->disk()->delete($path);
        }
    }

    private function folder(string $folder): string
    {
        $now = CarbonImmutable::instance($this->clock->now());

        return trim($folder, '/').'/'.$now->format('Y/m');
    }
}
