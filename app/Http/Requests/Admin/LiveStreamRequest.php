<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LiveStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('creatives.manage') ?? false;
    }

    public function rules(): array
    {
        return ['url' => ['required', 'string', 'max:2048'], 'name' => ['nullable', 'string', 'max:180']];
    }
}
