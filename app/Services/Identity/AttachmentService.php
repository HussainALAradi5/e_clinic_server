<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\AttachToData;
use App\Dtos\Identity\StartUploadData;
use App\Dtos\Identity\UploadResult;
use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\AuditAction;
use App\Enums\Identity\TaskType;
use App\Enums\Shared\GenericStatus;
use App\Events\Identity\AttachmentUploaded;
use App\Exceptions\Identity\AttachmentNotReadyException;
use App\Exceptions\Identity\AttachmentTypeNotAllowedException;
use App\Exceptions\Identity\UploadLimitExceededException;
use App\Exceptions\Identity\UploadSessionInvalidException;
use App\Exceptions\Shared\DomainException;
use App\Interfaces\Identity\AttachmentServiceInterface;
use App\Interfaces\Identity\AuditLogServiceInterface;
use App\Interfaces\Identity\BackgroundTaskServiceInterface;
use App\Interfaces\Shared\ReleasedToPatient;
use App\Models\Identity\Attachment;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\UploadSession;
use App\Models\Identity\User;
use App\Services\Base\BaseEntityService;
use App\Services\Shared\FileStorage;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends BaseEntityService<Attachment>
 */
final class AttachmentService extends BaseEntityService implements AttachmentServiceInterface
{
    private const MB = 1024 * 1024;

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly FileStorage $files,
        private readonly ClinicClock $clock,
        private readonly AuditLogServiceInterface $audit,
        private readonly BackgroundTaskServiceInterface $tasks,
    ) {
        parent::__construct($listQuery);
    }

    public function upload(array $files, AttachToData $owner): UploadResult
    {
        $limits = Config::array('attachments.multipart');
        $total = array_sum(array_map(fn (UploadedFile $f): int => (int) $f->getSize(), $files));
        if (count($files) > $limits['max_files'] || $total > $limits['max_total_mb'] * self::MB) {
            throw new UploadLimitExceededException(context: ['max_files' => $limits['max_files'], 'max_total_mb' => $limits['max_total_mb']]);
        }

        $this->ownerFor($owner, 'write');
        $result = new UploadResult;

        foreach ($files as $index => $file) {
            try {
                if ($file->getSize() > $limits['max_file_mb'] * self::MB) {
                    throw new UploadLimitExceededException(__('errors.UPLOAD_LIMIT_EXCEEDED'));
                }
                $result->saved[] = DB::transaction(fn (): Attachment => $this->uploadOne($file, $owner));
            } catch (DomainException $e) {
                $result->failed[] = ['index' => $index, 'name' => $this->cleanName($file->getClientOriginalName()), 'code' => $e->errorCode(), 'message' => $e->getMessage()];
            }
        }

        return $result;
    }

    public function uploadOne(UploadedFile $file, AttachToData $owner): Attachment
    {
        $record = $this->ownerFor($owner, 'write');
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $rules = $this->assertAllowed($owner->category, $mime, (int) $file->getSize());

        $sha256 = (string) hash_file('sha256', (string) $file->getRealPath());
        $duplicate = Attachment::query()->where([
            'attachable_type' => $record->getMorphClass(),
            'attachable_id' => $record->getKey(),
            'sha256' => $sha256,
        ])->first();
        if ($duplicate !== null) {
            return $duplicate;
        }

        $attachment = Attachment::query()->create([
            'attachable_type' => $record->getMorphClass(),
            'attachable_id' => $record->getKey(),
            'category' => $owner->category,
            'original_name' => $this->cleanName($file->getClientOriginalName()),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'disk' => $this->files->diskName(),
            'path' => $this->files->storePrivate($file, 'attachments'),
            'sha256' => $sha256,
            'is_sensitive' => (bool) ($rules['sensitive'] ?? false),
            'description' => $owner->description,
        ]);

        AttachmentUploaded::dispatch($attachment);

        return $attachment;
    }

    public function startResumable(StartUploadData $data): UploadSession
    {
        $config = Config::array('attachments.resumable');
        if ($data->sizeBytes > $config['max_mb'] * self::MB) {
            throw new UploadLimitExceededException(context: ['max_mb' => $config['max_mb']]);
        }
        $this->assertAllowed($data->category, $data->mimeType, $data->sizeBytes, declared: true);

        $path = $this->files->newPath('uploads', 'part');
        $this->files->disk()->put($path, '');

        return UploadSession::query()->create([
            'user_id' => $this->user()->id,
            'original_name' => $this->cleanName($data->fileName),
            'mime_type' => $data->mimeType,
            'category' => $data->category,
            'size_bytes' => $data->sizeBytes,
            'chunk_size' => $config['chunk_bytes'],
            'received_bytes' => 0,
            'sha256' => $data->sha256,
            'temp_path' => $path,
            'expires_at' => $this->clock->now()->addHours($config['expires_hours']),
        ]);
    }

    public function appendChunk(UploadSession $upload, $chunk, int $offset): UploadSession
    {
        return DB::transaction(function () use ($upload, $chunk, $offset): UploadSession {
            $upload = UploadSession::query()->lockForUpdate()->findOrFail($upload->id);
            if ($upload->isFinal() || $upload->expires_at->isPast() || $offset !== $upload->received_bytes) {
                throw new UploadSessionInvalidException(context: ['received_bytes' => $upload->received_bytes]);
            }

            $target = fopen($this->files->absolutePath($upload->temp_path), 'ab');
            $written = $target === false ? false : stream_copy_to_stream($chunk, $target, $upload->size_bytes - $upload->received_bytes + 1);
            if ($target !== false) {
                fclose($target);
            }
            if ($written === false || $upload->received_bytes + $written > $upload->size_bytes) {
                throw new UploadSessionInvalidException(context: ['received_bytes' => $upload->received_bytes]);
            }

            $upload->received_bytes += $written;
            $upload->expires_at = $this->clock->now()->addHours(Config::integer('attachments.resumable.expires_hours'));
            if ($upload->status === GenericStatus::Pending) {
                $upload->start();
            } else {
                $upload->save();
            }

            return $upload;
        });
    }

    public function completeResumable(UploadSession $upload, AttachToData $owner): Attachment
    {
        $absolute = $this->files->absolutePath($upload->temp_path);
        if ($upload->isFinal() || $upload->received_bytes !== $upload->size_bytes
            || ($upload->sha256 !== null && ! hash_equals($upload->sha256, (string) hash_file('sha256', $absolute)))) {
            throw new UploadSessionInvalidException(context: ['received_bytes' => $upload->received_bytes]);
        }

        $owner = new AttachToData($owner->attachableType, $owner->attachableId, $upload->category, $owner->description);
        $attachment = DB::transaction(function () use ($upload, $owner, $absolute): Attachment {
            $attachment = $this->uploadOne(new UploadedFile($absolute, $upload->original_name, null, null, true), $owner);
            $upload->attachment_id = $attachment->id;
            $upload->complete();

            return $attachment;
        });

        $this->files->delete($upload->temp_path);

        return $attachment;
    }

    public function findUpload(string $id): UploadSession
    {
        return UploadSession::query()->where('user_id', $this->user()->id)->findOrFail($id);
    }

    public function cancelResumable(UploadSession $upload): void
    {
        if (! $upload->isFinal()) {
            $upload->cancel();
        }
        $this->files->delete($upload->temp_path);
    }

    public function zip(array $attachmentIds): BackgroundTask
    {
        $attachments = $this->query()->whereKey($attachmentIds)->get();
        if ($attachments->count() !== count(array_unique($attachmentIds))) {
            throw (new ModelNotFoundException)->setModel(Attachment::class);
        }
        $attachments->each(fn (Attachment $a) => $this->assertDownloadable($a));

        if ($attachments->sum('size_bytes') > Config::integer('attachments.zip.max_mb') * self::MB) {
            throw new UploadLimitExceededException(context: ['max_mb' => Config::integer('attachments.zip.max_mb')]);
        }

        return $this->tasks->dispatch(TaskType::AttachmentsZip, ['attachment_ids' => $attachmentIds], $this->user());
    }

    public function list(ListQuery $query, ?string $attachableType = null, ?string $attachableId = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->query();
        if ($attachableType !== null && $attachableId !== null) {
            $record = $this->ownerFor(new AttachToData($attachableType, $attachableId, AttachmentCategory::Other), 'read');
            // Owner access rules apply to its files: whoever can read the record sees all of them.
            $builder = Attachment::query()->where('attachable_type', $record->getMorphClass())->where('attachable_id', $record->getKey());
        }

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): Attachment
    {
        return $this->query()->findOrFail($id);
    }

    public function findWithDeleted(string $id): Attachment
    {
        return parent::findWithDeleted($id);
    }

    public function downloadUrl(Attachment $attachment, bool $inline = false): array
    {
        $this->assertDownloadable($attachment);
        $this->audit->record(AuditAction::Exported, $attachment, [], ['file' => $attachment->original_name], 'identity');

        return $this->files->temporaryUrl($attachment->path, $attachment->original_name, inline: $inline);
    }

    public function assertDownloadable(Attachment $attachment): void
    {
        if (! $attachment->isDownloadable()) {
            throw new AttachmentNotReadyException(context: ['scan_status' => $attachment->scan_status->value]);
        }
        if ($attachment->is_sensitive && ! $this->user()->can('patients.view_medical') && ! $this->releasedToUser($attachment)) {
            throw new AuthorizationException;
        }
    }

    public function deleteAttachment(Attachment $attachment): void
    {
        $this->delete($attachment);
    }

    public function archiveAttachment(Attachment $attachment): Attachment
    {
        return $attachment->isArchived() ? $attachment : $this->archive($attachment);
    }

    public function unarchiveAttachment(Attachment $attachment): Attachment
    {
        return $attachment->isArchived() ? $this->unarchive($attachment) : $attachment;
    }

    public function restoreAttachment(Attachment $attachment): Attachment
    {
        return $attachment->isDeleted() ? $this->restore($attachment) : $attachment;
    }

    /**
     * @return Builder<Attachment>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Attachment::query(), 'attachments', Auth::user());
    }

    /** Sensitive file of a record released to the signed-in patient it is about (e.g. their completed result). */
    private function releasedToUser(Attachment $attachment): bool
    {
        /** @var class-string<Model>|null $model */
        $model = Config::get("attachments.owners.{$attachment->attachable_type}.model");
        $owner = $model !== null ? $model::query()->find($attachment->attachable_id) : null;

        return $owner instanceof ReleasedToPatient && $owner->isReleasedTo($this->user());
    }

    /**
     * Resolves the whitelisted owner record and checks read / write access to it.
     */
    private function ownerFor(AttachToData $owner, string $mode): Model
    {
        /** @var array{model: class-string<Model>, resource: string}|null $definition */
        $definition = Config::get("attachments.owners.{$owner->attachableType}");
        if ($definition === null) {
            throw new AttachmentTypeNotAllowedException(context: ['errors' => ['attachable_type' => [__('validation.in', ['attribute' => 'attachable type'])]]]);
        }

        $record = $definition['model']::query()->findOrFail($owner->attachableId);
        $r = $definition['resource'];
        $expression = $mode === 'write' ? "({$r}.read_all|{$r}.read_own)&{$r}.write" : "{$r}.read_all|{$r}.read_own";

        if (! PermissionExpression::parse($expression)->evaluate($this->user(), $record)) {
            throw (new ModelNotFoundException)->setModel($definition['model']);
        }

        return $record;
    }

    /**
     * @return array{mimes: list<string>, max_mb: int, sensitive?: bool}
     */
    private function assertAllowed(AttachmentCategory $category, string $mime, int $size, bool $declared = false): array
    {
        /** @var array{mimes: list<string>, max_mb: int, sensitive?: bool} $rules */
        $rules = Config::array("attachments.categories.{$category->value}");
        $max = $declared ? max($rules['max_mb'], 1) : $rules['max_mb'];

        if (! in_array($mime, $rules['mimes'], true) || $size > $max * self::MB || $size <= 0) {
            throw new AttachmentTypeNotAllowedException(context: ['category' => $category->value, 'allowed' => $rules['mimes'], 'max_mb' => $rules['max_mb']]);
        }

        return $rules;
    }

    private function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name));

        return Str::limit($name !== '' ? $name : 'file', 250, '');
    }

    private function user(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
