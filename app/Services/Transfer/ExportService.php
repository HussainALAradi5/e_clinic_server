<?php

declare(strict_types=1);

namespace App\Services\Transfer;

use App\Dtos\Bulk\BulkResourceDefinition;
use App\Dtos\Transfer\ExportData;
use App\Dtos\Transfer\ExportResult;
use App\Dtos\Transfer\ExportValue;
use App\Enums\Shared\ExportFormat;
use App\Exceptions\Transfer\ExportTooLargeException;
use App\Exceptions\Transfer\TransferNotAllowedException;
use App\Interfaces\Shared\TaskDispatcher;
use App\Services\Shared\FileStorage;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Transfer\Pdf\PdfTableWriter;
use App\Utilities\Transfer\Xlsx\XlsxWriter;
use Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Exports a list (the selected rows, or everything matching the filters on screen) as Excel, PDF or CSV, with the
 * same visibility rules as the list itself. Small exports return the file at once; large ones run as a background
 * task (`list_export`) whose result the user downloads when it is ready.
 */
final class ExportService
{
    public const TASK_TYPE = 'list_export';

    private const CHUNK = 500;

    public function __construct(
        private readonly TransferGuard $guard,
        private readonly ApplyListQuery $listQuery,
        private readonly FileStorage $files,
        private readonly ClinicClock $clock,
    ) {}

    public function export(string $resource, ExportData $data, Authenticatable $user): ExportResult
    {
        $definition = $this->guard->forExport($resource, $user);
        if (! in_array($data->format, $definition->exportFormats(), true)) {
            throw new TransferNotAllowedException;
        }

        $columns = $this->columns($definition, $data->columns);
        $rows = (clone $this->query($definition, $data, $user))->count();

        $pdfLimit = Config::integer('clinic.transfer.pdf_max_rows');
        if ($data->format === ExportFormat::Pdf && $rows > $pdfLimit) {
            throw new ExportTooLargeException(__('errors.EXPORT_TOO_LARGE', ['max' => number_format($pdfLimit)]));
        }

        if ($rows > Config::integer('clinic.transfer.export_sync_limit')) {
            $task = app(TaskDispatcher::class)->start(self::TASK_TYPE, ['resource' => $resource, ...$data->toArray()], $user);

            return new ExportResult(taskId: (string) $task->getKey(), rows: $rows);
        }

        $path = $this->files->newPath('exports', $data->format->value);
        $count = $this->write($definition, $data, $columns, $user, $this->files->absolutePath($path));

        return new ExportResult($path, $this->downloadName($definition, $data->format), $data->format->mimeType(), rows: $count);
    }

    /**
     * Writes the export file; returns the number of rows. `$onProgress(done)` may return false to stop (cancelled task).
     *
     * @param  array<string, string>  $columns  field => label
     * @param  (callable(int): bool)|null  $onProgress
     */
    public function write(BulkResourceDefinition $definition, ExportData $data, array $columns, Authenticatable $user, string $absolutePath, ?callable $onProgress = null): int
    {
        $headers = array_values($columns);
        $done = 0;
        $rows = function () use ($definition, $data, $columns, $user, $onProgress, &$done): Generator {
            foreach ($this->query($definition, $data, $user)->lazy(self::CHUNK) as $model) {
                yield $this->cells($definition, $model, array_keys($columns));
                if (++$done % self::CHUNK === 0 && $onProgress !== null && ! $onProgress($done)) {
                    return;
                }
            }
        };

        match ($data->format) {
            ExportFormat::Xlsx => $this->xlsx($absolutePath, $headers, $rows(), array_keys($columns)),
            ExportFormat::Csv => $this->csv($absolutePath, $headers, $rows()),
            ExportFormat::Pdf => (new PdfTableWriter)->write($absolutePath, $this->title($definition), $this->subtitle(), $headers, $rows()),
        };

        return $done;
    }

    /**
     * Requested columns that the list offers, in the requested order (all list columns when none are given),
     * followed by the resource's export-only columns.
     *
     * @param  list<string>  $requested
     * @return array<string, string>
     */
    public function columns(BulkResourceDefinition $definition, array $requested): array
    {
        $offered = $definition->config->columns();
        $columns = [];
        foreach ($requested as $field) {
            if (isset($offered[$field])) {
                $columns[$field] = $offered[$field];
            }
        }

        return [...($columns === [] ? $offered : $columns), ...$definition->exportOnlyColumns()];
    }

    /**
     * The list query: selected ids or the ListQuery filters, limited to what the user may read.
     *
     * @return Builder<Model>
     */
    public function query(BulkResourceDefinition $definition, ExportData $data, Authenticatable $user): Builder
    {
        $builder = $definition->applyExportQuery($definition->model::query()->with($definition->config->includeNames()));

        if ($definition->readPermission() !== PermissionExpression::AUTHENTICATED) {
            $builder = OwnershipScope::apply($builder, $definition->permissionResource(), $user);
        }

        $filter = $data->ids !== null ? array_intersect_key($data->filter, array_flip(['sort'])) : $data->filter;
        $builder = $this->listQuery->apply($builder, ListQuery::fromArray($filter, $definition->config));

        return $data->ids !== null ? $builder->whereKey($data->ids) : $builder;
    }

    public function downloadName(BulkResourceDefinition $definition, ExportFormat $format): string
    {
        return Str::slug($this->title($definition)).'-'.$this->clock->toClinic($this->clock->now())->format('Ymd-Hi').'.'.$format->value;
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function cells(BulkResourceDefinition $definition, Model $model, array $fields): array
    {
        $resource = (string) $definition->exportResource();
        /** @var array<string, mixed> $row */
        $row = (new $resource($model))->resolve(request());

        $computed = $definition->exportValues();

        return array_map(
            fn (string $field): string => isset($computed[$field]) ? ($computed[$field])($model) : ExportValue::of($model, $row, $field),
            $fields,
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     * @param  list<string>  $fields
     */
    private function xlsx(string $path, array $headers, iterable $rows, array $fields): void
    {
        $widths = array_map(fn (string $h): int => max(12, min(40, mb_strlen($h) + 6)), $headers);
        $xlsx = new XlsxWriter;
        $sheet = $xlsx->addSheet('Data', $widths)->header($headers);
        foreach ($rows as $row) {
            $sheet->row($row);
        }
        $xlsx->save($path);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    private function csv(string $path, array $headers, iterable $rows): void
    {
        $out = fopen($path, 'wb') ?: throw new RuntimeException('Cannot write the export file.');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            // A leading = + - @ would run as a formula when the CSV is opened in Excel.
            fputcsv($out, array_map(fn (string $v): string => preg_match('/^[=+\-@\t\r]/', $v) === 1 ? "'".$v : $v, $row));
        }
        fclose($out);
    }

    private function title(BulkResourceDefinition $definition): string
    {
        return Str::headline($definition->resource);
    }

    private function subtitle(): string
    {
        return 'Exported '.$this->clock->toClinic($this->clock->now())->format('j M Y, H:i').' from e-Clinic';
    }
}
