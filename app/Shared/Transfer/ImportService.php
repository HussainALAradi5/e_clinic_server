<?php

declare(strict_types=1);

namespace App\Shared\Transfer;

use App\Shared\Bulk\BulkResourceDefinition;
use App\Shared\Contracts\RendersApiError;
use App\Shared\Transfer\Contracts\ImportDefinition;
use App\Shared\Transfer\Data\ImportResult;
use App\Shared\Transfer\Exceptions\ImportFailedException;
use App\Shared\Transfer\Exceptions\InvalidImportFileException;
use App\Shared\Transfer\Xlsx\XlsxReader;
use App\Shared\Transfer\Xlsx\XlsxWriter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Excel import in one request: every row is read and validated, then all rows are saved in one transaction through
 * the resource's own service. Any invalid row → nothing is saved and every problem is reported with its row and
 * column. The template (columns, an example sheet and instructions) comes from the same ImportDefinition.
 */
final class ImportService
{
    public const DATA_SHEET = 'Data';

    private const MAX_REPORTED_ERRORS = 200;

    public function __construct(
        private readonly TransferGuard $guard,
        private readonly XlsxReader $reader,
    ) {}

    /** Writes the template workbook to `$path`; returns the download file name. */
    public function template(string $resource, Authenticatable $user, string $path): string
    {
        $importer = $this->importer($this->guard->forImport($resource, $user));
        $columns = $importer->columns();
        $headers = array_map(fn (ImportColumn $c): string => $c->header.($c->required ? ' *' : ''), $columns);
        $widths = array_map(fn (ImportColumn $c): int => max(14, min(36, mb_strlen($c->header) + 8, mb_strlen($c->example) + 6)), $columns);

        $xlsx = new XlsxWriter;
        $xlsx->addSheet(self::DATA_SHEET, $widths)->header($headers);
        $xlsx->addSheet('Example', $widths)->header($headers)->row(array_map(fn (ImportColumn $c): string => $c->example, $columns));

        $help = $xlsx->addSheet('Instructions', [26, 12, 46, 26, 50], freezeHeader: false)
            ->row(['How to import '.Str::headline($resource)], XlsxWriter::STYLE_BOLD)
            ->row(['Fill one row per record on the "'.self::DATA_SHEET.'" sheet, keep the header row, then upload the file. Columns marked * are required. See the "Example" sheet for a filled-in row.'], XlsxWriter::STYLE_WRAP)
            ->row(['If any row has a problem nothing is imported, and you get the list of rows to fix.'], XlsxWriter::STYLE_WRAP)
            ->row([])
            ->header(['Column', 'Required', 'Format', 'Example', 'Notes']);
        foreach ($columns as $column) {
            $help->row([$column->header, $column->required ? 'Yes' : 'No', $column->format(), $column->example, $column->help], XlsxWriter::STYLE_WRAP);
        }

        $xlsx->save($path);

        return Str::slug($resource).'-import-template.xlsx';
    }

    public function import(string $resource, UploadedFile $file, Authenticatable $user): ImportResult
    {
        $importer = $this->importer($this->guard->forImport($resource, $user));
        $rows = $this->readRows($importer, $file);

        // Messages name the spreadsheet column ("The Mobile field …"), not the API field.
        $attributes = [];
        foreach ($importer->columns() as $column) {
            $attributes[$column->field] = $column->header;
        }

        $errors = [];
        $valid = [];
        foreach ($rows as $rowNumber => $input) {
            $validator = Validator::make($input, $importer->rules(), [], $attributes);
            if ($validator->fails()) {
                $errors = [...$errors, ...$this->rowErrors($importer, $rowNumber, $validator->errors()->toArray())];

                continue;
            }
            $valid[$rowNumber] = $validator->validated();
        }

        if ($errors !== []) {
            throw $this->failed($errors);
        }

        $ids = DB::transaction(function () use ($importer, $valid, $user): array {
            $ids = [];
            $errors = [];
            foreach ($valid as $rowNumber => $input) {
                try {
                    $ids[] = (string) $importer->import($input, $user)->getKey();
                } catch (ValidationException $e) {
                    $errors = [...$errors, ...$this->rowErrors($importer, $rowNumber, $e->errors())];
                } catch (RendersApiError $e) {
                    $errors[] = ['row' => $rowNumber, 'column' => null, 'message' => $e->getMessage()];
                }
            }
            if ($errors !== []) {
                throw $this->failed($errors); // rolls the whole import back
            }

            return $ids;
        });

        return new ImportResult($resource, count($ids), $ids);
    }

    private function importer(BulkResourceDefinition $definition): ImportDefinition
    {
        return $definition->importDefinition() ?? throw new InvalidImportFileException;
    }

    /**
     * Reads the Data sheet: header row → ImportColumn by header (with or without " *") or field name.
     *
     * @return array<int, array<string, mixed>> Excel row number => input
     */
    private function readRows(ImportDefinition $importer, UploadedFile $file): array
    {
        $columns = $importer->columns();
        $rows = [];
        $map = null;

        try {
            foreach ($this->reader->rows((string) $file->getRealPath(), self::DATA_SHEET) as $rowNumber => $cells) {
                if ($map === null) {
                    $map = $this->headerMap($columns, $cells);

                    continue;
                }
                if (implode('', $cells) === '') {
                    continue;
                }
                if (count($rows) >= Config::integer('clinic.transfer.import_max_rows')) {
                    throw new InvalidImportFileException(__('errors.IMPORT_TOO_MANY_ROWS', ['max' => Config::integer('clinic.transfer.import_max_rows')]));
                }

                $input = [];
                foreach ($columns as $column) {
                    $index = $map[$column->field] ?? null;
                    $input[$column->field] = $index === null ? null : $column->read($cells[$index] ?? '');
                }
                // Dotted fields (address.city) become nested input, as in the JSON API.
                $rows[$rowNumber] = Arr::undot(array_filter($input, fn (?string $v): bool => $v !== null));
            }
        } catch (InvalidImportFileException $e) {
            throw $e;
        } catch (Throwable) {
            throw new InvalidImportFileException;
        }

        if ($rows === []) {
            throw new InvalidImportFileException(__('errors.IMPORT_EMPTY'));
        }

        return $rows;
    }

    /**
     * @param  list<ImportColumn>  $columns
     * @param  array<int, string>  $cells
     * @return array<string, int> field => cell index
     */
    private function headerMap(array $columns, array $cells): array
    {
        $normalise = fn (string $text): string => Str::lower(trim(rtrim(trim($text), '*')));
        $map = [];
        foreach ($cells as $index => $cell) {
            foreach ($columns as $column) {
                if (in_array($normalise($cell), [$normalise($column->header), Str::lower($column->field)], true)) {
                    $map[$column->field] = $index;
                }
            }
        }

        $missing = array_filter($columns, fn (ImportColumn $c): bool => $c->required && ! isset($map[$c->field]));
        if ($missing !== []) {
            throw new InvalidImportFileException(__('errors.IMPORT_MISSING_COLUMNS', [
                'columns' => implode(', ', array_map(fn (ImportColumn $c): string => $c->header, $missing)),
            ]));
        }

        return $map;
    }

    /**
     * @param  array<string, array<int, string>>  $messages  field (may be nested, e.g. address.city) => messages
     * @return list<array{row: int, column: ?string, message: string}>
     */
    private function rowErrors(ImportDefinition $importer, int $row, array $messages): array
    {
        $headers = [];
        foreach ($importer->columns() as $column) {
            $headers[$column->field] = $column->header;
        }

        $errors = [];
        foreach ($messages as $field => $list) {
            foreach ($list as $message) {
                $errors[] = ['row' => $row, 'column' => $headers[$field] ?? $headers[Str::before($field, '.')] ?? null, 'message' => $message];
            }
        }

        return $errors;
    }

    /**
     * @param  list<array{row: int, column: ?string, message: string}>  $errors
     */
    private function failed(array $errors): ImportFailedException
    {
        $rows = count(array_unique(array_column($errors, 'row')));

        return new ImportFailedException(trans_choice('errors.IMPORT_FAILED', $rows, ['count' => $rows]), [
            'rows' => array_slice($errors, 0, self::MAX_REPORTED_ERRORS),
            'error_count' => count($errors),
        ]);
    }
}
