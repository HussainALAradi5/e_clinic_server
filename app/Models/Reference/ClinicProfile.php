<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Models\Base\BaseModel;
use App\Models\Identity\Attachment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The clinic's own profile (CLP-000001), one row: the name, tagline and contacts printed on every PDF letterhead, the
 * logo and the clinic stamp (attachments). Admins change it; everyone reads it.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ?string $tagline
 * @property ?string $address
 * @property ?string $phone
 * @property ?string $email
 * @property ?string $website
 * @property ?string $logo_attachment_id
 * @property ?string $stamp_attachment_id
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?Attachment $logo
 * @property-read ?Attachment $stamp
 */
class ClinicProfile extends BaseModel
{
    public static function codePrefix(): string
    {
        return 'CLP';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    /** @return BelongsTo<Attachment, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'logo_attachment_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'stamp_attachment_id');
    }

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }
}
