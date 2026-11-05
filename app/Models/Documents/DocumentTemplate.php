<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Enums\Documents\WrittenDocumentKind;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable wording for documents written at a visit (TPL-000001): name, description, kind and a rich-text body
 * (bold, italic, lists, headings…) with `{{placeholders}}` filled from the visit. Ready-made ones (`is_system`) come
 * with the clinic and are read-only; staff copy them or write their own from scratch.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property WrittenDocumentKind $kind
 * @property string $body
 * @property bool $is_system
 * @property bool $is_active
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class DocumentTemplate extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'TPL';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('templates')
            ->sortable('name', 'code', 'kind', 'updated_at')
            ->filterable('kind', ['eq', 'in'], enum: WrittenDocumentKind::class)
            ->filterable('is_system', ['eq'], type: 'boolean')
            ->filterable('is_active', ['eq'], type: 'boolean')
            ->searchIn('name', 'description', 'code')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('kind', 'Kind')
            ->column('is_system', 'Ready-made')
            ->dateFields('updated_at')
            ->defaultSort('name');
    }

    /** Ready-made templates belong to the clinic; staff own the ones they wrote. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return ! $this->is_system && $this->created_by === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('document_templates.is_system', false)->where('document_templates.created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'kind' => WrittenDocumentKind::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
