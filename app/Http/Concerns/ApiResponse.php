<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

/**
 * Standard success envelope: { success: true, message, data, meta?, links? }.
 */
trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta  endpoint-specific meta for unpaginated lists (e.g. has_more)
     */
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $body = ['success' => true, 'message' => $message, 'data' => $this->resolve($data)];

        return new JsonResponse($meta === [] ? $body : [...$body, 'meta' => $meta], $status);
    }

    protected function created(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function accepted(?string $message = null, array $meta = [], mixed $data = null): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => $message, 'data' => $this->resolve($data), 'meta' => $meta ?: null], 202);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * Paginated list: data + meta (page or cursor info, echoed sort / filters / date) + links.
     *
     * @param  ResourceCollection|LengthAwarePaginator<int, mixed>|CursorPaginator<int, mixed>  $items
     * @param  array<string, mixed>  $extraMeta  endpoint-specific meta (e.g. unread_count)
     */
    protected function paginated(ResourceCollection|LengthAwarePaginator|CursorPaginator $items, ?ListQuery $query = null, ?string $message = null, array $extraMeta = []): JsonResponse
    {
        $paginator = $items instanceof ResourceCollection ? $items->resource : $items;

        $meta = $paginator instanceof LengthAwarePaginator
            ? ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]
            : ['per_page' => $paginator->perPage(), 'next_cursor' => $paginator->nextCursor()?->encode(), 'prev_cursor' => $paginator->previousCursor()?->encode()];

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $this->resolve($items),
            'meta' => [...$meta, ...($query?->toMeta() ?? []), ...$extraMeta],
            'links' => ['next' => $paginator->nextPageUrl(), 'prev' => $paginator->previousPageUrl()],
        ]);
    }

    private function resolve(mixed $data): mixed
    {
        return match (true) {
            $data instanceof JsonResource => $data->resolve(request()),
            $data instanceof Arrayable => $data->toArray(),
            $data instanceof LengthAwarePaginator, $data instanceof CursorPaginator => $data->items(),
            default => $data,
        };
    }
}
