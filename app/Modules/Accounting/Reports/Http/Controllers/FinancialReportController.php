<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Reports\Http\Controllers;

use App\Modules\Accounting\Reports\Contracts\FinancialReportServiceInterface;
use App\Modules\Accounting\Reports\Http\Requests\ReportRequest;
use App\Shared\Enums\ExportFormat;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class FinancialReportController
{
    use ApiResponse;

    public function __construct(private readonly FinancialReportServiceInterface $reports) {}

    /** Any report; the type comes from the route (`trial-balance` → trial_balance). */
    public function show(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->build($request->type(), $request->params())->toArray());
    }

    public function export(ReportRequest $request): BinaryFileResponse
    {
        $format = ExportFormat::from($request->string('format')->toString());
        [$path, $name] = $this->reports->export($request->type(), $request->params(), $format);

        return response()->download($path, $name, ['Content-Type' => $format->mimeType()])->deleteFileAfterSend();
    }
}
