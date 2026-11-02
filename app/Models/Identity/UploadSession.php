<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Shared\GenericStatus;
use App\Interfaces\Shared\HasStateMachine;
use App\Services\Shared\FileStorage;
use App\Traits\Shared\HasGenericStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Resumable (chunked) upload in progress. `received_bytes` is the resume offset.
 *
 * @property string $id
 * @property string $user_id
 * @property string $original_name
 * @property string $mime_type
 * @property AttachmentCategory $category
 * @property int $size_bytes
 * @property int $chunk_size
 * @property int $received_bytes
 * @property ?string $sha256
 * @property string $temp_path
 * @property GenericStatus $status
 * @property ?string $attachment_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class UploadSession extends Model implements HasStateMachine
{
    use HasGenericStatus;
    use HasUuids;
    use Prunable;

    protected $guarded = ['id'];

    protected $hidden = ['temp_path'];

    public function cancellationReasonColumn(): ?string
    {
        return null;
    }

    /**
     * Finished or expired sessions (temporary files are removed by the service / the prune hook).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $q) => $q
            ->whereIn('status', [GenericStatus::Completed, GenericStatus::Cancelled])
            ->orWhere('expires_at', '<', now()));
    }

    protected function pruning(): void
    {
        app(FileStorage::class)->delete($this->temp_path);
    }

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'size_bytes' => 'integer',
            'chunk_size' => 'integer',
            'received_bytes' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
