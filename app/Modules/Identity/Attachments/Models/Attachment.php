<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Models;

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A file attached to any record (polymorphic). Stored privately, virus-scanned, downloadable via signed links only.
 *
 * @property string $id
 * @property string $code
 * @property string $attachable_type
 * @property string $attachable_id
 * @property AttachmentCategory $category
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $disk
 * @property string $path
 * @property string $sha256
 * @property FileScanStatus $scan_status
 * @property bool $is_sensitive
 * @property ?string $description
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Attachment extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    protected $hidden = ['disk', 'path'];

    protected $attributes = ['scan_status' => 'pending', 'is_sensitive' => false];

    public static function codePrefix(): string
    {
        return 'ATT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('attachments')
            ->sortable('code', 'original_name', 'size_bytes', 'category', 'created_at')
            ->filterable('category', ['eq', 'in'], enum: AttachmentCategory::class)
            ->filterable('scan_status', ['eq', 'in'], enum: FileScanStatus::class)
            ->filterable('attachable_type', ['eq'])
            ->filterable('attachable_id', ['eq'])
            ->filterable('mime_type', ['eq', 'starts'])
            ->filterable('is_sensitive', ['eq'], type: 'boolean')
            ->searchIn('code', 'original_name', 'description')
            ->column('code', 'Code')
            ->column('original_name', 'File')
            ->column('category', 'Category')
            ->column('size_bytes', 'Size')
            ->column('scan_status', 'Scan')
            ->column('attachable_type', 'Owner')
            ->column('created_at', 'Uploaded')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('created_by', $user->getAuthIdentifier());
    }

    public function isDownloadable(): bool
    {
        return $this->scan_status === FileScanStatus::Clean;
    }

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'scan_status' => FileScanStatus::class,
            'size_bytes' => 'integer',
            'is_sensitive' => 'boolean',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
