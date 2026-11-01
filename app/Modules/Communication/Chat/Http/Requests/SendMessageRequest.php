<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Requests;

use App\Modules\Communication\Chat\Data\SendMessageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Text and/or files (multipart); `voice` marks a single recorded audio file; `mentions` are member ids. */
final class SendMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:files', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
            'voice' => ['nullable', 'boolean'],
            'mentions' => ['nullable', 'array', 'max:50'],
            'mentions.*' => ['uuid', 'distinct'],
            // A message of the same chat (checked by the service).
            'reply_to_id' => ['nullable', 'uuid'],
        ];
    }

    public function toData(): SendMessageData
    {
        $body = trim($this->string('body')->toString());
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $this->file('files', []), fn (mixed $f): bool => $f instanceof UploadedFile));

        return new SendMessageData(
            body: $body === '' ? null : $body,
            files: $files,
            mentions: array_values(array_map('strval', (array) $this->input('mentions', []))),
            voice: $this->boolean('voice') && count($files) === 1,
            replyToId: $this->filled('reply_to_id') ? $this->string('reply_to_id')->toString() : null,
        );
    }
}
