<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Models;

use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Emergency access ("break the glass") to a patient outside the clinician's care, for a limited time.
 *
 * @property string $id
 * @property string $patient_id
 * @property string $user_id
 * @property string $reason
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
class PatientAccessGrant extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['patient_id', 'user_id', 'reason', 'expires_at'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
