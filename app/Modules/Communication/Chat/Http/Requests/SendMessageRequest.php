<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:4000']];
    }

    /** The text as written, without surrounding blank lines. */
    public function body(): string
    {
        return trim($this->string('body')->toString());
    }
}
