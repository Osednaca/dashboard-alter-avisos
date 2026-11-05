<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPlaylistRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ];
    }
}
