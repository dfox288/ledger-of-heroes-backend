<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('media.max_file_size', 2048);
        $mimetypes = implode(',', config('media.accepted_mimetypes', []));

        return [
            'file' => [
                'required',
                'file',
                'image',
                "max:{$maxSize}",
                "mimetypes:{$mimetypes}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.image' => 'The file must be an image.',
            'file.max' => 'The image must not exceed 2MB.',
            'file.mimetypes' => 'The image must be a JPEG, PNG, or WebP.',
        ];
    }
}
