<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Models\Identity\Attachment;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;

interface SignatureServiceInterface
{
    /** The user's signature, or null when they have none. */
    public function of(User $user): ?Attachment;

    /** Saves a drawn (canvas PNG) or uploaded signature, replacing the previous one; staff only. */
    public function set(User $user, UploadedFile $file): Attachment;

    public function remove(User $user): void;

    /** The person's signature as a data URI for PDFs, or null. */
    public function dataUri(string $personId): ?string;
}
