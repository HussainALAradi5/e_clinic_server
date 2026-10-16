<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ResourceSchemaBuilder;
use App\Shared\Support\EnumCatalog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /meta/enums and GET /meta/resources/{resource}.
 */
final class MetaController extends Controller
{
    use ApiResponse;

    public const ENUMS_CACHE_KEY = 'meta:enums';

    public function enums(EnumCatalog $catalog): JsonResponse
    {
        return $this->ok(Cache::rememberForever(self::ENUMS_CACHE_KEY.':'.app()->getLocale(), fn (): array => $catalog->all()));
    }

    public function resource(Request $request, string $resource, ResourceSchemaBuilder $schemas): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return $this->ok($schemas->build($resource, $user)->toArray());
    }
}
