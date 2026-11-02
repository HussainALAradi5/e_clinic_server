<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Models\Appointments\Icd10Code;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loads ICD-10 codes from CSV (`code,title,title_ar,chapter,is_billable`, header row) — insert or update by code,
 * so a newer catalog can be loaded over an older one. Codes already used by records are never removed.
 */
final class Icd10Importer
{
    /** Rows per upsert statement. */
    private const CHUNK = 500;

    public function import(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $header = array_map(fn (mixed $h): string => strtolower(trim((string) $h)), (array) fgetcsv($handle));
        $count = 0;
        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            $values = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $code = strtoupper(trim((string) ($values['code'] ?? '')));
            if ($code === '' || trim((string) ($values['title'] ?? '')) === '') {
                continue;
            }
            $batch[] = [
                'id' => (string) Str::uuid7(),
                'code' => $code,
                'title' => trim((string) $values['title']),
                'title_ar' => ($values['title_ar'] ?? '') !== '' ? trim((string) $values['title_ar']) : null,
                'chapter' => ($values['chapter'] ?? '') !== '' ? trim((string) $values['chapter']) : null,
                'is_billable' => ! in_array(strtolower(trim((string) ($values['is_billable'] ?? '1'))), ['0', 'false', 'no'], true),
            ];
            if (count($batch) === self::CHUNK) {
                $count += $this->flush($batch);
                $batch = [];
            }
        }
        fclose($handle);

        return $count + $this->flush($batch);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flush(array $rows): int
    {
        if ($rows !== []) {
            Icd10Code::query()->upsert($rows, ['code'], ['title', 'title_ar', 'chapter', 'is_billable']);
        }

        return count($rows);
    }
}
