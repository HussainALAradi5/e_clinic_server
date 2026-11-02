<?php

declare(strict_types=1);

namespace App\Utilities\Communication\Models;

use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply on a support ticket, by the requester or an admin.
 *
 * @property string $id
 * @property string $ticket_id
 * @property string $author_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read User $author
 */
class TicketComment extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['ticket_id', 'author_id', 'body'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
