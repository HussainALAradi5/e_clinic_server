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
use Illuminate\Support\Facades\Config;

/**
 * GET /meta/enums and GET /meta/resources/{resource}.
 */
final class MetaController extends Controller
{
    use ApiResponse;

    public const ENUMS_CACHE_KEY = 'meta:enums';

    public function enums(EnumCatalog $catalog): JsonResponse
    {
        // The key carries a fingerprint of the published enum list, so an added enum refreshes the cache by itself.
        $fingerprint = md5((string) json_encode(Config::array('clinic.enums')));

        return $this->ok(Cache::rememberForever(self::ENUMS_CACHE_KEY.':'.app()->getLocale().':'.$fingerprint, fn (): array => $catalog->all()));
    }

    public function resource(Request $request, string $resource, ResourceSchemaBuilder $schemas): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return $this->ok($schemas->build($resource, $user)->toArray());
    }
}
