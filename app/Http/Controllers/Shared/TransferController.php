<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ExportRequest;
use App\Http\Requests\Shared\ImportRequest;
use App\Services\Shared\FileStorage;
use App\Services\Transfer\ExportService;
use App\Services\Transfer\ImportService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export / import of any list registered as exportable / importable (routes from Route::dataTransfer()):
 *   POST /<resource>/export           → the file (small) or 202 + background task (large)
 *   GET  /<resource>/import/template  → Excel template with an example and instructions
 *   POST /<resource>/import           → all rows saved, or 422 IMPORT_FAILED listing every row problem
 */
final class TransferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ExportService $exports,
        private readonly ImportService $imports,
        private readonly FileStorage $files,
    ) {}

    public function export(ExportRequest $request): BinaryFileResponse|JsonResponse
    {
        $result = $this->exports->export($this->resource($request), $request->toData(), $this->user($request));

        if ($result->queued()) {
            return $this->accepted(__('messages.export_queued'), data: ['task_id' => $result->taskId, 'rows' => $result->rows]);
        }

        return response()
            ->download($this->files->absolutePath((string) $result->path), $result->downloadName, ['Content-Type' => (string) $result->mimeType])
            ->deleteFileAfterSend();
    }

    public function template(Request $request): BinaryFileResponse
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tpl');
        $name = $this->imports->template($this->resource($request), $this->user($request), $path);

        return response()
            ->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    public function import(ImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $result = $this->imports->import($this->resource($request), is_array($file) ? $file[0] : $file, $this->user($request));

        return $this->ok($result->toArray(), trans_choice('messages.import_done', $result->created, ['count' => $result->created]));
    }

    private function resource(Request $request): string
    {
        return (string) $request->route('resource');
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user() ?? throw new AuthenticationException;
    }
}
