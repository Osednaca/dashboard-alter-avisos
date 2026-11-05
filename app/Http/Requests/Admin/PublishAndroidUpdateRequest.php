<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PublishAndroidUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('system.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'apk' => ['required', 'file', 'extensions:apk', 'max:'.config('android_updates.max_upload_kb')],
            'forceUpdate' => ['sometimes', 'boolean'],
            'changelog' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (strlen($value) > 16384 || ! mb_check_encoding($value, 'UTF-8'))) {
                    $fail('Las notas deben usar UTF-8 y no superar 16 KiB.');
                }
            }],
        ];
    }
}
