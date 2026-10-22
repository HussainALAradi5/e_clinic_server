<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Console;

use App\Modules\Appointments\Records\Services\Icd10Importer;
use Illuminate\Console\Command;

/** `php artisan icd10:import path/to/icd10.csv` — loads a full ICD-10 / ICD-10-CM catalog. */
final class ImportIcd10Command extends Command
{
    protected $signature = 'icd10:import {file : CSV with columns code,title,title_ar,chapter,is_billable}';

    protected $description = 'Insert or update ICD-10 diagnosis codes from a CSV file';

    public function handle(Icd10Importer $importer): int
    {
        $count = $importer->import((string) $this->argument('file'));
        $this->info("{$count} ICD-10 codes loaded.");

        return self::SUCCESS;
    }
}
