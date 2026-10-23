<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\HasStateMachine;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\FilterFieldType;
use App\Shared\Enums\GenericStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasGenericStatus;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
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
            ->dateFields('scheduled_date', 'created_at', 'completed_at')
            ->searchIn('name', 'code')
            ->column('code', 'Code')
            ->column('name', 'Name')
            ->column('status', 'Status')
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
