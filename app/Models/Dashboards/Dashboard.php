<?php

declare(strict_types=1);

namespace App\Models\Dashboards;

use App\Enums\Dashboards\DashboardSection;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;

/**
 * A saved dashboard (`DSH-000001`) of one section. `widgets` lists which metrics it shows, in what size and order;
 * the figures are computed when it is read. A section has at most one default dashboard (a partial unique index).
 * No relations of its own; inherits BaseModel (code, soft delete, blame, audit).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property DashboardSection $section
 * @property bool $is_default
 * @property list<array{metric: string, title: ?string, size: string, position: int}> $widgets
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Dashboard extends BaseModel
{
    use HasListQuery;

    protected $attributes = ['is_default' => false];

    public static function codePrefix(): string
    {
        return 'DSH';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('dashboards')
            ->sortable('code', 'name', 'section', 'created_at')
            ->filterable('section', ['eq', 'in'], enum: DashboardSection::class)
            ->filterable('is_default', ['eq'], type: 'boolean')
            ->searchIn('code', 'name')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('section', 'Section')
            ->column('is_default', 'Default')
            ->dateFields('created_at')
            ->defaultSort('name');
    }

    protected function casts(): array
    {
        return [
            'section' => DashboardSection::class,
            'is_default' => 'boolean',
            'widgets' => 'array',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
