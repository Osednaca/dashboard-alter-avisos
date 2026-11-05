<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourceParser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidateLiveCampaign
{
    public function handle(Campaign $campaign): void
    {
        $campaign->loadMissing('creatives.mediaAsset');
        foreach ($campaign->creatives as $creative) {
            if ($creative->mediaAsset?->type !== MediaType::LiveStream) {
                continue;
            }
            app(LiveSourceParser::class)->parse($creative->mediaAsset->metadata['live']['original_url']);
            $config = $creative->configuration ?? [];
            $validator = Validator::make($config, [
                'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
                'display_mode' => ['required', 'in:advertising_zone,business_zone,fullscreen'],
                'audio' => ['required', 'boolean'], 'size_acknowledged' => ['accepted'],
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['creatives' => 'Revisa el horario, la zona y la confirmación del tamaño del directo antes de publicar.']);
            }
            if (! empty($config['fallback_media_id']) && ! MediaAsset::query()->advertising()->ready()->whereIn('type', ['image', 'video'])->whereKey($config['fallback_media_id'])->exists()) {
                throw ValidationException::withMessages(['creatives' => 'El archivo de respaldo ya no está disponible. Selecciona otro o la lista normal.']);
            }
        }
    }
}
