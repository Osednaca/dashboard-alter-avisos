<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BusinessMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('business.media.upload');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxVideo = (int) config('signage.uploads.max_video_kb');

        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4', "max:{$maxVideo}"],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('file');

            if (! $file) {
                return;
            }

            $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

            if ($isImage && $file->getSize() > config('signage.uploads.max_image_kb') * 1024) {
                $validator->errors()->add('file', 'La imagen supera el tamaño máximo permitido.');
            }

            $allowed = $isImage
                ? config('signage.uploads.image_mimes')
                : config('signage.uploads.video_mimes');

            if (! in_array($file->getMimeType(), $allowed, true)) {
                $validator->errors()->add('file', 'El tipo de archivo no está permitido.');
            }
        });
    }
}
