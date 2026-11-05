<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LibrarySelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The destination controller enforces its existing permissions.
    }

    public function rules(): array
    {
        return [
            'media_ids' => ['nullable', 'array', 'max:'.($this->routeIs('campaigns.create') ? 30 : 100)],
            'media_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
