<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('business.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'audio_volume' => ['nullable', 'integer', 'between:0,100'],
            'notify_email' => ['boolean'],
            'notify_offline' => ['boolean'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $logo = $this->file('logo');

            if ($logo && ! in_array($logo->getMimeType(), config('signage.uploads.image_mimes'), true)) {
                $validator->errors()->add('logo', 'El logo debe ser una imagen JPG, PNG o WebP.');
            }
        });
    }
}
