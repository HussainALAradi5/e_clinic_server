<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A signature image: drawn on screen (exported as PNG) or uploaded (PNG / JPEG), up to 1 MB.
 */
final class SignatureRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signature' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:1024', 'dimensions:min_width=80,min_height=30,max_width=2000,max_height=1000'],
        ];
    }

    public function signature(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('signature');
    }
}
