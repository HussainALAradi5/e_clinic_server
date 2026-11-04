<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\UserType;
use App\Exceptions\Identity\SignatureNotAllowedException;
use App\Interfaces\Identity\SignatureServiceInterface;
use App\Models\Identity\Attachment;
use App\Models\Identity\User;
use App\Services\Shared\FileStorage;
use App\Utilities\Shared\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A staff member's signature, drawn on screen or uploaded as an image, printed on the documents they issue (sick
 * leaves, results, prescriptions…). Kept as the latest `signature` attachment of their person record.
 */
final class SignatureService implements SignatureServiceInterface
{
    public function __construct(
        private readonly StoredImage $images,
        private readonly FileStorage $files,
    ) {}

    public function of(User $user): ?Attachment
    {
        return $this->latest($user->getKey());
    }

    public function set(User $user, UploadedFile $file): Attachment
    {
        if ($user->user_type === UserType::Patient) {
            throw new SignatureNotAllowedException;
        }

        return DB::transaction(function () use ($user, $file): Attachment {
            $this->remove($user);

            return $this->images->store($file, 'person', (string) $user->getKey(), AttachmentCategory::Signature, 'signatures');
        });
    }

    public function remove(User $user): void
    {
        Attachment::query()->where('attachable_type', 'person')->where('attachable_id', $user->getKey())
            ->where('category', AttachmentCategory::Signature->value)->where('is_deleted', false)
            ->get()->each->delete();
    }

    public function dataUri(string $personId): ?string
    {
        $signature = $this->latest($personId);
        $bytes = $signature !== null ? $this->files->disk()->get($signature->path) : null;

        return $bytes === null || $signature === null ? null : 'data:'.$signature->mime_type.';base64,'.base64_encode($bytes);
    }

    private function latest(string $personId): ?Attachment
    {
        return Attachment::query()->where('attachable_type', 'person')->where('attachable_id', $personId)
            ->where('category', AttachmentCategory::Signature->value)->where('is_deleted', false)
            ->latest('created_at')->first();
    }
}
