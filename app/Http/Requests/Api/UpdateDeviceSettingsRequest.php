<?php

namespace App\Http\Requests\Api;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Playlists\Models\PlaylistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Device && $this->user()->status !== DeviceStatus::Disabled;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['prohibited'],
            'settings' => ['required', 'array:split,business_percentage,orientation,audio_mode,rotation,transition', 'min:1'],
            'settings.split' => ['sometimes', 'required', Rule::in(['side_by_side', 'top_bottom'])],
            'settings.business_percentage' => ['sometimes', 'required', 'integer', 'between:1,99'],
            'settings.orientation' => ['sometimes', 'required', Rule::in(['landscape', 'portrait'])],
            'settings.audio_mode' => ['sometimes', 'required', Rule::in(['business', 'advertising', 'none'])],
            'settings.rotation' => ['sometimes', 'required', 'integer', Rule::in([0, 90, 180, 270])],
            'settings.transition' => ['sometimes', 'required', Rule::in(['playlist', ...array_keys(PlaylistItem::TRANSITIONS)])],
        ];
    }
}
