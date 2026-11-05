<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Support\Facades\URL;

class LiveSourcePayload
{
    public function forAsset(MediaAsset $asset): ?array
    {
        if ($asset->type !== MediaType::LiveStream) {
            return null;
        }
        $source = app(LiveSourceParser::class)->parse($asset->metadata['live']['original_url']);

        return $source + [
            'allowed_hosts' => config('live.provider_hosts.'.$source['provider'], []),
            'embed_url' => URL::signedRoute('live.embed', ['media' => $asset->id, 'audio' => 0]),
            'embed_audio_url' => URL::signedRoute('live.embed', ['media' => $asset->id, 'audio' => 1]),
            'hls_hosts' => config('live.hls_hosts'),
            'connect_timeout_seconds' => max(5, min(120, config('live.connect_timeout_seconds'))),
            'retry_seconds' => max(10, config('live.retry_seconds')),
            'max_retry_seconds' => max(30, config('live.max_retry_seconds')),
            'unverified_seconds' => max(30, config('live.unverified_seconds')),
        ];
    }
}
