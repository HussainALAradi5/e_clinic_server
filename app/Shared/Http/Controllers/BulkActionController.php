<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Bulk\BulkActionService;
use App\Shared\Data\BulkActionData;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Http\Requests\BulkActionRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

/**
 * POST /<resource>/bulk — the resource name comes from the route default set by Route::bulkActions().
 */
final class BulkActionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BulkActionService $bulk) {}

    public function __invoke(BulkActionRequest $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $result = $this->bulk->run((string) $request->route('resource'), BulkActionData::fromRequest($request), $user);

        return $result->queued
            ? $this->accepted(__('messages.bulk_queued'), data: $result->toArray())
            : $this->ok($result->toArray());
    }
}
