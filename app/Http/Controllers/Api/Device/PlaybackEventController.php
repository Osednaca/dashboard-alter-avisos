<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Devices\Models\Device;
use App\Domain\Playback\Actions\IngestPlaybackBatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybackEventController extends Controller
{
    public function store(Request $request, IngestPlaybackBatch $ingest): JsonResponse
    {
        $limit = (int) config('signage.device.playback_batch_limit');

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', "max:{$limit}"],
            'events.*.media_asset_id' => ['required', 'integer', 'exists:media_assets,id'],
            'events.*.campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'events.*.creative_id' => ['nullable', 'integer', 'exists:campaign_creatives,id'],
            'events.*.playlist_id' => ['nullable', 'integer', 'exists:playlists,id'],
            'events.*.started_at' => ['required', 'date'],
            'events.*.completed_at' => ['nullable', 'date'],
            'events.*.duration_played' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'events.*.completed' => ['boolean'],
            'events.*.error_code' => ['nullable', 'string', 'max:40'],
            'events.*.manifest_version' => ['nullable', 'integer'],
            'events.*.metadata' => ['nullable', 'array:event,started_event,provider,live_source_id,verified'],
            'events.*.metadata.started_event' => ['nullable', 'in:stream_started,fallback_started'],
            'events.*.metadata.event' => ['required_with:events.*.metadata', 'in:stream_started,stream_completed,stream_failed,fallback_started,fallback_completed'],
            'events.*.metadata.provider' => ['required_with:events.*.metadata', 'in:hls,youtube,twitch,kick'],
            'events.*.metadata.live_source_id' => ['required_with:events.*.metadata', 'integer'],
            'events.*.metadata.verified' => ['boolean'],
        ]);

        /** @var Device $device */
        $device = $request->user();

        $count = $ingest->handle($device, $data['events']);

        return response()->json([
            'ingested' => $count,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
