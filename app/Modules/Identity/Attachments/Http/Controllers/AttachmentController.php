<?php

declare(strict_types=1);

namespace App\Modules\Identity\Attachments\Http\Controllers;

use App\Modules\Identity\Attachments\Contracts\AttachmentServiceInterface;
use App\Modules\Identity\Attachments\Http\Requests\CompleteUploadRequest;
use App\Modules\Identity\Attachments\Http\Requests\StartUploadRequest;
use App\Modules\Identity\Attachments\Http\Requests\UploadAttachmentsRequest;
use App\Modules\Identity\Attachments\Http\Requests\ZipAttachmentsRequest;
use App\Modules\Identity\Attachments\Http\Resources\AttachmentResource;
use App\Modules\Identity\Attachments\Http\Resources\UploadSessionResource;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Tasks\Http\Resources\BackgroundTaskResource;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AttachmentController
{
    use ApiResponse;

    public function __construct(private readonly AttachmentServiceInterface $attachments) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['attachable_type' => ['nullable', 'string', 'required_with:attachable_id'], 'attachable_id' => ['nullable', 'uuid', 'required_with:attachable_type']]);
        $query = ListQuery::fromRequest($request, Attachment::listQueryConfig());
        $page = $this->attachments->list($query, $request->query('attachable_type'), $request->query('attachable_id'));

        return $this->paginated(AttachmentResource::collection($page), $query);
    }

    public function show(string $attachment): JsonResponse
    {
        return $this->ok(new AttachmentResource($this->attachments->find($attachment)));
    }

    /** 201 all saved · 207 partial · 422 none — body {saved, failed}. */
    public function store(UploadAttachmentsRequest $request): JsonResponse
    {
        $result = $this->attachments->upload($request->uploadedFiles(), $request->owner());

        return $this->ok([
            'saved' => AttachmentResource::collection($result->saved)->resolve($request),
            'failed' => $result->failed,
        ], status: $result->httpStatus());
    }

    public function startUpload(StartUploadRequest $request): JsonResponse
    {
        return $this->created(new UploadSessionResource($this->attachments->startResumable($request->toData())));
    }

    /** Raw chunk body; `Upload-Offset` must equal the current received_bytes. */
    public function appendChunk(Request $request, string $upload): JsonResponse
    {
        $offset = $request->header('Upload-Offset');
        abort_unless(is_numeric($offset), 400, 'Upload-Offset header is required.');

        $body = $request->getContent(true);

        return $this->ok(new UploadSessionResource($this->attachments->appendChunk($this->attachments->findUpload($upload), $body, (int) $offset)));
    }

    public function uploadStatus(string $upload): JsonResponse
    {
        return $this->ok(new UploadSessionResource($this->attachments->findUpload($upload)));
    }

    public function completeUpload(CompleteUploadRequest $request, string $upload): JsonResponse
    {
        return $this->created(new AttachmentResource($this->attachments->completeResumable($this->attachments->findUpload($upload), $request->owner())));
    }

    public function cancelUpload(string $upload): Response
    {
        $this->attachments->cancelResumable($this->attachments->findUpload($upload));

        return $this->noContent();
    }

    public function zip(ZipAttachmentsRequest $request): JsonResponse
    {
        $task = $this->attachments->zip($request->ids());

        return $this->accepted(__('messages.task_queued'), ['task_id' => $task->id], new BackgroundTaskResource($task));
    }

    public function download(Request $request, string $attachment): JsonResponse
    {
        return $this->ok($this->attachments->downloadUrl($this->attachments->find($attachment), $request->boolean('inline')));
    }

    public function destroy(string $attachment): Response
    {
        $this->attachments->deleteAttachment($this->attachments->find($attachment));

        return $this->noContent();
    }

    public function archive(string $attachment): JsonResponse
    {
        return $this->ok(new AttachmentResource($this->attachments->archiveAttachment($this->attachments->find($attachment))));
    }

    public function unarchive(string $attachment): JsonResponse
    {
        return $this->ok(new AttachmentResource($this->attachments->unarchiveAttachment($this->attachments->find($attachment))));
    }

    public function restore(string $attachment): JsonResponse
    {
        return $this->ok(new AttachmentResource($this->attachments->restoreAttachment($this->attachments->findWithDeleted($attachment))));
    }
}
