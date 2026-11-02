<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Models\Identity\User;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\FilterOptions;
use App\Utilities\Query\ListQueryConfig;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal entry header. Amounts are numeric(16,3) strings; posted entries are immutable (DB trigger) and
 * corrected only by a reversal entry.
 *
 * @property string $id
 * @property string $code
 * @property ?string $entry_number
 * @property CarbonImmutable $entry_date
 * @property string $fiscal_period_id
 * @property JournalEntryType $type
 * @property JournalEntryStatus $status
 * @property ?string $source_type
 * @property ?string $source_id
 * @property string $description
 * @property string $total_debit
 * @property string $total_credit
 * @property ?string $reversal_of_id
 * @property ?string $posted_by
 * @property ?CarbonImmutable $posted_at
 * @property ?string $created_by
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property-read Collection<int, JournalEntryLine> $lines
 * @property-read FiscalPeriod $fiscalPeriod
 * @property-read ?JournalEntry $reversalOf
 * @property-read ?JournalEntry $reversedBy
 * @property-read ?User $poster
 * @property-read ?User $creator
 * @property-read ?Model $source
 */
class JournalEntry extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'JNL';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('journal')
            ->sortable('entry_date', 'entry_number', 'code', 'total_debit', 'status', 'type', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: JournalEntryType::class)
            ->filterable('status', ['eq', 'in'], enum: JournalEntryStatus::class)
            ->filterable('source_type', ['eq', 'in'], label: 'Created from', options: FilterOptions::inUse(self::class, 'source_type', FilterOptions::inWords()))
            ->filterable('source_id', ['eq'], hidden: true)
            ->filterable('fiscal_period_id', ['eq'], label: 'Month')
            ->filterable('entry_number', ['eq', 'starts'])
            ->filterable('code', ['eq', 'starts'])
            ->filterable('total_debit', ['gte', 'lte', 'between'], type: 'money')
            ->column('entry_number', 'Number')
            ->column('entry_date', 'Date')
            ->column('description', 'Description')
            ->column('type', 'Type')
            ->column('status', 'Status')
            ->column('total_debit', 'Amount')
            ->column('code', 'Code')
            ->dateFields('entry_date', 'posted_at', 'created_at')
            ->defaultSort('-entry_date');
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isManual(): bool
    {
        return in_array($this->type, [JournalEntryType::Manual, JournalEntryType::Adjustment], true);
    }

    public function amount(): BigDecimal
    {
        return BigDecimal::of($this->total_debit);
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<JournalEntry, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
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
        return $query->where('journal_entries.created_by', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => JournalEntryType::class,
            'status' => JournalEntryStatus::class,
            'entry_date' => 'immutable_date',
            'total_debit' => 'decimal:3',
            'total_credit' => 'decimal:3',
            'posted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
