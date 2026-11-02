<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\SavedViewRequest;
use App\Http\Resources\Identity\SavedViewResource;
use App\Interfaces\Identity\ListViewServiceInterface;
use App\Models\Identity\SavedView;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ListViewController
{
    use ApiResponse;

    public function __construct(private readonly ListViewServiceInterface $views) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['resource' => ['nullable', 'string', 'max:50']]);
        $query = ListQuery::fromRequest($request, SavedView::listQueryConfig());
        $resource = $request->query('resource');

        return $this->paginated(SavedViewResource::collection($this->views->list($query, is_string($resource) ? $resource : null)), $query);
    }

    public function store(SavedViewRequest $request): JsonResponse
    {
        return $this->created(new SavedViewResource($this->views->create($request->toData())));
    }

    public function update(SavedViewRequest $request, string $view): JsonResponse
    {
        return $this->ok(new SavedViewResource($this->views->update($this->views->find($view), $request->toData())));
    }

    public function setDefault(string $view): JsonResponse
    {
        return $this->ok(new SavedViewResource($this->views->setDefault($this->views->find($view))));
    }

    public function destroy(string $view): Response
    {
        $this->views->delete($this->views->find($view));

        return $this->noContent();
    }
}
