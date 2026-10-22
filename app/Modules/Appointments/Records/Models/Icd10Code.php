<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ICD-10 diagnosis (seeded reference data; `php artisan icd10:import` loads a full catalog from CSV).
 *
 * @property string $id
 * @property string $code
 * @property string $title
 * @property ?string $title_ar
 * @property ?string $chapter
 * @property bool $is_billable
 */
class Icd10Code extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'icd10_codes';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_billable' => 'boolean'];
    }
}
