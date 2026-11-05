<?php

namespace App\Http\Requests\Business;

use App\Domain\Playlists\Models\PlaylistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaylistItemRequest extends FormRequest
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
            'media_asset_id' => ['required', 'integer', 'exists:media_assets,id'],
            'duration' => ['required', 'integer', 'between:3,600'],
            'transition' => ['required', Rule::in(array_keys(PlaylistItem::TRANSITIONS))],
        ];
    }
}
