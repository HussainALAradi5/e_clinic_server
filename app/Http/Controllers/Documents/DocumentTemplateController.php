<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Documents\DocumentTemplateRequest;
use App\Http\Resources\Documents\DocumentTemplateResource;
use App\Interfaces\Documents\DocumentTemplateServiceInterface;
use App\Models\Documents\DocumentTemplate;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Document templates: list (ready-made + own), read, write from scratch, change, copy, delete; and the fields a
 * template can name.
 */
final class DocumentTemplateController
{
    use ApiResponse;

    public function __construct(private readonly DocumentTemplateServiceInterface $templates) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, DocumentTemplate::listQueryConfig());

        return $this->paginated(DocumentTemplateResource::collection($this->templates->listTemplates($query)), $query);
    }

    public function show(string $template): JsonResponse
    {
        return $this->ok(new DocumentTemplateResource($this->templates->findTemplate($template)));
    }

    public function store(DocumentTemplateRequest $request): JsonResponse
    {
        return $this->created(new DocumentTemplateResource($this->templates->createTemplate($request->toData())));
    }

    public function update(DocumentTemplateRequest $request, string $template): JsonResponse
    {
        return $this->ok(new DocumentTemplateResource($this->templates->updateTemplate($this->templates->findTemplate($template), $request->toData())));
    }

    public function duplicate(string $template): JsonResponse
    {
        return $this->created(new DocumentTemplateResource($this->templates->duplicateTemplate($this->templates->findTemplate($template))));
    }

    public function destroy(string $template): Response
    {
        $this->templates->deleteTemplate($this->templates->findTemplate($template));

        return $this->noContent();
    }

    public function placeholders(): JsonResponse
    {
        return $this->ok($this->templates->placeholders());
    }
}
