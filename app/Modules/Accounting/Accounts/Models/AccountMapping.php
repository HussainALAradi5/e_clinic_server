<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Models;

use App\Enums\Accounting\AccountMappingKey;
use App\Shared\Contracts\Auditable;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\Blameable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which real (leaf) account an automatic posting uses for a logical role such as cash on hand.
 *
 * @property string $id
 * @property AccountMappingKey $key
 * @property string $account_id
 * @property-read Account $account
 */
class AccountMapping extends Model implements Auditable
{
    use AuditsChanges;
    use Blameable;
    use HasUuids;

    protected $fillable = ['key', 'account_id'];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return ['key' => AccountMappingKey::class];
    }
}
