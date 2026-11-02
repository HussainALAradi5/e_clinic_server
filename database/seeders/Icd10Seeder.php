<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Appointments\Icd10Importer;
use Illuminate\Database\Seeder;

/**
 * Common primary-care ICD-10 codes (English + Arabic) so diagnoses work out of the box; load the full catalog with
 * `php artisan icd10:import`.
 */
final class Icd10Seeder extends Seeder
{
    public function run(Icd10Importer $importer): void
    {
        $importer->import(database_path('data/icd10_common.csv'));
    }
}
