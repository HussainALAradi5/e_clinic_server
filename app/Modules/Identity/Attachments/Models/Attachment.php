<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Models;

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Shared\Access\OwnershipScope;
use App\Shared\Access\PermissionExpression;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use App\Shared\Query\FilterOptions;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

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
            ->filterable('scan_status', ['eq', 'in'], enum: FileScanStatus::class, label: 'Virus check')
            ->filterable('attachable_type', ['eq', 'in'], label: 'Attached to', options: FilterOptions::words(array_keys((array) config('attachments.owners'))))
            ->filterable('attachable_id', ['eq'], hidden: true)
            ->filterable('mime_type', ['eq', 'in'], label: 'File type', options: FilterOptions::fileTypes(array_values(array_unique(array_merge(...array_column((array) config('attachments.categories'), 'mimes'))))))
            ->filterable('is_sensitive', ['eq'], type: 'boolean', label: 'Medical / private')
            ->searchIn('description')
            ->column('code', 'Code')
            ->column('original_name', 'File')
            ->column('category', 'Category')
            ->column('size_bytes', 'Size')
            ->column('scan_status', 'Virus check')
            ->column('attachable_type', 'Attached to')
            ->column('created_at', 'Uploaded')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    /** Uploaded by the user, or a file of a record the user may read (follows the owner record). */
    public function isOwnedBy(Authenticatable $user): bool
    {
        if ($this->created_by === $user->getAuthIdentifier()) {
            return true;
        }
        $owner = self::owners()[$this->attachable_type] ?? null;
        $record = $owner !== null ? $owner['model']::query()->find($this->attachable_id) : null;

        return $record !== null && PermissionExpression::parse("{$owner['resource']}.read_all|{$owner['resource']}.read_own")->evaluate($user, $record);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('created_by', $user->getAuthIdentifier());
            foreach (self::owners() as $type => ['model' => $model, 'resource' => $resource]) {
                if (PermissionExpression::parse("{$resource}.read_all|{$resource}.read_own")->evaluate($user)) {
                    $readable = OwnershipScope::apply($model::query(), $resource, $user)->select((new $model)->getQualifiedKeyName());
                    $q->orWhere(fn (Builder $o) => $o->where('attachable_type', $type)->whereIn('attachable_id', $readable));
                }
            }
        });
    }

    /** @return array<string, array{model: class-string<Model>, resource: string}> */
    private static function owners(): array
    {
        /** @var array<string, array{model: class-string<Model>, resource: string}> */
        return Config::array('attachments.owners');
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
