<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Dtos\Identity\AttachToData;
use App\Dtos\Identity\StartUploadData;
use App\Dtos\Identity\UploadResult;
use App\Models\Identity\Attachment;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\UploadSession;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface AttachmentServiceInterface
{
    /**
     * One or many files (multipart `files[]`) for one record; every file is validated and stored independently.
     *
     * @param  list<UploadedFile>  $files
     */
    public function upload(array $files, AttachToData $owner): UploadResult;

    /** **Internal** — single-file pipeline (owner access, category rules, MIME from content, SHA-256, scan). */
    public function uploadOne(UploadedFile $file, AttachToData $owner): Attachment;

    public function startResumable(StartUploadData $data): UploadSession;

    /**
     * @param  resource  $chunk
     */
    public function appendChunk(UploadSession $upload, $chunk, int $offset): UploadSession;

    public function completeResumable(UploadSession $upload, AttachToData $owner): Attachment;

    /** The caller's own active upload session. */
    public function findUpload(string $id): UploadSession;

    public function cancelResumable(UploadSession $upload): void;

    /**
     * Several files as one ZIP (background task).
     *
     * @param  list<string>  $attachmentIds
     */
    public function zip(array $attachmentIds): BackgroundTask;

    /**
     * Attachments list (optionally of one owner record — read access to the owner required).
     *
     * @return LengthAwarePaginator<int, Attachment>|CursorPaginator<int, Attachment>
     */
    public function list(ListQuery $query, ?string $attachableType = null, ?string $attachableId = null): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): Attachment;

    public function findWithDeleted(string $id): Attachment;

    /**
     * 5-minute signed link (file must be clean; sensitive files need patients.view_medical). Audited.
     *
     * @return array{url: string, expires_at: string}
     */
    public function downloadUrl(Attachment $attachment, bool $inline = false): array;

    /** Throws when the current user may not download the file (used by the ZIP task too). */
    public function assertDownloadable(Attachment $attachment): void;

    public function deleteAttachment(Attachment $attachment): void;

    public function archiveAttachment(Attachment $attachment): Attachment;

    public function unarchiveAttachment(Attachment $attachment): Attachment;

    public function restoreAttachment(Attachment $attachment): Attachment;
}
