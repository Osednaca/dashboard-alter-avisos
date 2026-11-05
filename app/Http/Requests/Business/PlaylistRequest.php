<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('business.playlists.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
        ];
    }
}
