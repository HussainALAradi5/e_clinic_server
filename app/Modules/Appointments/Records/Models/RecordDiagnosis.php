<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $medical_record_id
 * @property string $icd10_code_id
 * @property bool $is_primary
 * @property ?string $notes
 * @property-read Icd10Code $code
 */
class RecordDiagnosis extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'record_diagnoses';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<Icd10Code, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class, 'icd10_code_id');
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
