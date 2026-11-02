<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Casts\MoneyCast;
use App\Enums\Shared\FilterFieldType;
use App\Enums\Shared\GenericStatus;
use App\Interfaces\Shared\HasStateMachine;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasGenericStatus;
use App\Traits\Shared\HasListQuery;
use App\Traits\Shared\HasVersion;
use App\Utilities\Query\FilterOptions;
use App\Utilities\Query\ListQueryConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only GenericEntity used to exercise the shared kernel.
 *
 * @property string $name
 * @property int $priority
 * @property ?string $owner_id
 */
class Widget extends BaseModel implements HasStateMachine, OwnedByUser
{
    use HasGenericStatus;
    use HasListQuery;
    use HasVersion;

    protected $table = 'widgets';

    public static function codePrefix(): string
    {
        return 'WID';
    }

    public static function createTable(): void
    {
        Schema::create('widgets', function (Blueprint $table): void {
            $table->genericEntity();
            $table->blameable();
            $table->version();
            $table->string('name');
            $table->string('status', 20);
            $table->unsignedSmallInteger('priority')->default(1);
            $table->money('price')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->uuid('owner_id')->nullable();
        });
    }

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'scheduled_date' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->sortable('name', 'priority', 'created_at', 'scheduled_date')
            ->filterable('status', ['eq', 'ne', 'in', 'nin'], enum: GenericStatus::class)
            ->filterable('priority', ['eq', 'gt', 'gte', 'lt', 'lte', 'between'], type: FilterFieldType::Number)
            ->filterable('name', ['eq', 'like', 'starts'])
            ->filterable('completed_at', ['null'], type: FilterFieldType::Datetime, hidden: true)
            ->filterable('owner', ['eq'], column: 'owner_id', resource: 'users', label: 'Responsible')
            ->filterable('cancellation_reason', ['eq', 'in'], label: 'Reason', options: FilterOptions::inUse(self::class, 'cancellation_reason', FilterOptions::inWords()))
            ->filterable('scheduled_date', ['eq', 'gte', 'lte', 'between'], type: FilterFieldType::Date)
            ->dateFields('scheduled_date', 'created_at', 'completed_at')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('status', 'Status')
            ->column('cancellation_reason', 'Reason')
            ->defaultSort('-created_at')
            ->maxDateRangeDays(366);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->owner_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('owner_id', $user->getAuthIdentifier());
    }
}
