<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Documents\DraftWrittenDocumentRequest;
use App\Http\Requests\Documents\WrittenDocumentRequest;
use App\Http\Resources\Documents\WrittenDocumentResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Documents\WrittenDocumentServiceInterface;
use App\Models\Documents\WrittenDocument;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Documents written from a visit session: list and read them, fill a template from the visit ("auto generate"),
 * and save one.
 */
final class WrittenDocumentController
{
    use ApiResponse;

    public function __construct(
        private readonly WrittenDocumentServiceInterface $documents,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, WrittenDocument::listQueryConfig());

        return $this->paginated(WrittenDocumentResource::collection($this->documents->listWritten($query)), $query);
    }

    public function show(string $document): JsonResponse
    {
        return $this->ok(new WrittenDocumentResource($this->documents->findWritten($document)));
    }

    public function draft(DraftWrittenDocumentRequest $request, string $appointment): JsonResponse
    {
        $body = $this->documents->draft($this->appointments->find($appointment), $request->kind(), $request->input('template_id'), $request->input('body'));

        return $this->ok(['body' => $body]);
    }

    public function store(WrittenDocumentRequest $request, string $appointment): JsonResponse
    {
        return $this->created(new WrittenDocumentResource($this->documents->write($this->appointments->find($appointment), $request->toData())));
    }
}
