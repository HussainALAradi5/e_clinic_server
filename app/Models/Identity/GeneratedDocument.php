<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Documents\DocumentType;
use App\Models\Base\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PDF the server generated of a record (`DOC-YYYY-NNNNNN`): which record and when, its unique file name, and the
 * attachment holding the file. Reused while the record is unchanged (`record_updated_at`), otherwise a new one is
 * made, so every version handed out stays on file.
 *
 * @property string $id
 * @property string $code
 * @property DocumentType $type
 * @property string $record_id
 * @property string $reference
 * @property string $file_name
 * @property string $attachment_id
 * @property ?CarbonImmutable $record_updated_at
 * @property int $size_bytes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Attachment $attachment
 */
class GeneratedDocument extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'DOC';
    }

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'record_updated_at' => 'immutable_datetime',
            'size_bytes' => 'integer',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
