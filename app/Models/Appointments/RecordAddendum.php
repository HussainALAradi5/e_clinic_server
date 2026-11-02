<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dated correction of a signed record (append-only).
 *
 * @property string $id
 * @property string $medical_record_id
 * @property string $text
 * @property ?string $created_by
 * @property CarbonImmutable $created_at
 * @property-read ?User $author
 */
class RecordAddendum extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'record_addenda';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['text' => 'encrypted', 'created_at' => 'immutable_datetime'];
    }
}
