<?php

namespace App\Http\Requests\Admin;

use App\Domain\Campaigns\Enums\CampaignTargetType;
use App\Domain\Media\Models\MediaAsset;
use App\Http\Requests\Admin\Concerns\ValidatesCampaignTargets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    use ValidatesCampaignTargets;

    public function authorize(): bool
    {
        return $this->user()->hasPermission(
            $this->route('campaign') ? 'campaigns.edit' : 'campaigns.create'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'advertiser_id' => ['required', 'integer', 'exists:advertisers,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'daily_start_time' => ['nullable', 'date_format:H:i'],
            'daily_end_time' => ['nullable', 'date_format:H:i', 'after:daily_start_time'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'priority' => ['required', 'integer', 'between:1,10'],
            'playback_goal' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'impressions_goal' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'publish' => ['boolean'],

            'creatives' => ['required', 'array', 'min:1', 'max:30'],
            'creatives.*.media_asset_id' => ['required', 'integer', Rule::exists('media_assets', 'id')->where(fn ($q) => $q->whereIn('id', MediaAsset::query()->advertising()->ready()->select('id')))],
            'creatives.*.duration' => ['required', 'integer', 'between:3,86400'],
            'creatives.*.weight' => ['required', 'integer', 'between:1,100'],
            'creatives.*.configuration' => ['nullable', 'array:display_mode,audio,starts_at,ends_at,fallback_media_id,size_acknowledged'],
            'creatives.*.configuration.display_mode' => ['required_with:creatives.*.configuration', 'in:advertising_zone,business_zone,fullscreen'],
            'creatives.*.configuration.audio' => ['boolean'],
            'creatives.*.configuration.starts_at' => ['required_with:creatives.*.configuration', 'date'],
            'creatives.*.configuration.ends_at' => ['required_with:creatives.*.configuration', 'date', 'after:creatives.*.configuration.starts_at'],
            'creatives.*.configuration.size_acknowledged' => ['boolean'],
            'creatives.*.configuration.fallback_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($q) => $q->whereIn('id', MediaAsset::query()->advertising()->ready()->whereIn('type', ['image', 'video'])->select('id')))],

            'targets' => ['required', 'array', 'min:1', 'max:200'],
            'targets.*' => ['required', 'array'],
            'targets.*.target_type' => ['bail', 'required', 'string', Rule::enum(CampaignTargetType::class)],
            'targets.*.target_id' => ['nullable', 'integer'],
            'targets.*.target_value' => ['nullable', 'string', 'max:160'],
            'targets.*.is_exclusion' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // After callbacks also run when the input failed the structural rules.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $liveIds = MediaAsset::query()->where('type', 'live_stream')->whereIn('id', array_column($this->input('creatives'), 'media_asset_id'))->pluck('id')->all();
            foreach ($this->input('creatives') as $index => $creative) {
                if (in_array((int) $creative['media_asset_id'], $liveIds, true)) {
                    if (empty($creative['configuration'])) {
                        $validator->errors()->add("creatives.{$index}.configuration", 'Configura el horario, la zona y el respaldo del directo.');
                    } elseif (empty($creative['configuration']['size_acknowledged'])) {
                        $validator->errors()->add("creatives.{$index}.configuration.size_acknowledged", 'Revisa el tamaño de las zonas antes de publicar el directo.');
                    }
                } elseif (! empty($creative['configuration'])) {
                    $validator->errors()->add("creatives.{$index}.configuration", 'La configuración en vivo solo corresponde a un directo.');
                }
            }

            $this->validateTargets($validator);
        });
    }
}
