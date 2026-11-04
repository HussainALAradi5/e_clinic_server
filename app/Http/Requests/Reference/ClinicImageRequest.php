<?php

declare(strict_types=1);

namespace App\Http\Requests\Reference;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The clinic logo or stamp: PNG or JPEG, up to 1 MB.
 */
final class ClinicImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=2048,max_height=2048'],
        ];
    }

    public function image(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('image');
    }
}
