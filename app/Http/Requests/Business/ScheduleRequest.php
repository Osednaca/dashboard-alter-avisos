<?php

namespace App\Http\Requests\Business;

use App\Domain\Playlists\Models\PlaylistItem;
use App\Support\BusinessAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('business.schedules.manage')
            && (! $this->has('items') || ($this->user()->hasPermission('business.playlists.manage')
                && $this->user()->hasPermission('business.media.view')
                && $this->user()->hasPermission('business.playlists.view')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'playlist_id' => ['required_without:items', Rule::prohibitedIf($this->has('items')), 'integer', Rule::exists('playlists', 'id')->where(fn ($query) => $query
                ->where('business_id', BusinessAccess::id())->where('type', 'business')->where('is_schedule_managed', false))],
            'items' => ['required_without:playlist_id', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:media_asset_id,duration_seconds,transition'],
            'items.*.media_asset_id' => ['required', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query
                ->where('owner_type', BusinessAccess::current()?->getMorphClass())
                ->where('owner_id', BusinessAccess::id())->whereIn('type', ['image', 'video'])->where('processing_status', 'ready'))],
            'items.*.duration_seconds' => ['required', 'integer', 'between:3,600'],
            'items.*.transition' => ['required', Rule::in(array_keys(PlaylistItem::TRANSITIONS))],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('business_id', BusinessAccess::id())],
            'daily_start_time' => ['nullable', 'required_with:daily_end_time', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'required_with:daily_start_time', 'date_format:H:i', 'after:daily_start_time'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'priority' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
