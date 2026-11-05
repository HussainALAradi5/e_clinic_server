<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Services\Shared\FileStorage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /files?t=…&expires=…&signature=… — streams a private file through a signed, short-lived link
 * (route middleware `signed`). The token is encrypted, so the storage path is never visible.
 */
final class FileController
{
    public function __invoke(Request $request): StreamedResponse
    {
        try {
            $file = FileStorage::decodeToken((string) $request->query('t'));
        } catch (DecryptException) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($file['disk']);
        if (! $disk->exists($file['path'])) {
            throw new NotFoundHttpException;
        }

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $file['inline']
            ? $disk->response($file['path'], $file['name'], $headers)
            : $disk->download($file['path'], $file['name'], $headers);
    }
}
