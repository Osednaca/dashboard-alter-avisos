<?php

namespace Tests\Feature;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourceParser;
use App\Domain\Playback\Models\PlaybackEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_live_campaign_requires_valid_schedule_fallback_and_size_review_and_restores_fields(): void
    {
        $this->actingAs($this->superAdmin())->postJson('/admin/live-streams', ['url' => 'https://kick.com/example'])->assertCreated();
        $live = MediaAsset::sole();
        $fallback = MediaAsset::factory()->image()->create();
        $config = ['display_mode' => 'fullscreen', 'audio' => true, 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'fallback_media_id' => $fallback->id, 'size_acknowledged' => true];
        $body = ['advertiser_id' => Advertiser::factory()->create()->id, 'name' => 'Live event',
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addWeek()->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $live->id, 'duration' => 10, 'weight' => 10, 'configuration' => $config]],
            'targets' => [['target_type' => 'city', 'target_value' => 'Bogotá']]];
        foreach ([['ends_at' => now()->subHour()->toIso8601String()], ['size_acknowledged' => false], ['fallback_media_id' => $live->id], ['display_mode' => 'anything']] as $patch) {
            $bad = $body;
            $bad['creatives'][0]['configuration'] = [...$config, ...$patch];
            $this->postJson('/admin/campaigns', $bad)->assertUnprocessable();
        }
        $this->post('/admin/campaigns', $body)->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->get('/admin/campaigns/'.$campaign->id.'/edit')->assertInertia(fn ($page) => $page->where('campaign.creatives.0.configuration', $config));
        $campaign->update(['status' => 'active']);
        $this->assertTrue($fallback->isLockedByActiveCampaign());
        $fallback->update(['processing_status' => 'failed']);
        $this->post('/admin/campaigns/'.$campaign->id.'/publish')->assertSessionHasErrors('creatives');
    }

    public function test_live_proof_uses_existing_batch_endpoint_with_provider_and_transition_metadata(): void
    {
        $device = Device::factory()->create();
        $asset = MediaAsset::factory()->create();
        $metadata = ['event' => 'stream_failed', 'started_event' => 'stream_started', 'provider' => 'hls', 'live_source_id' => $asset->id, 'verified' => true];
        $this->withToken($device->issueToken())->postJson('/api/v1/device/playback-events/batch', ['events' => [[
            'media_asset_id' => $asset->id, 'started_at' => now()->subMinute()->toIso8601String(), 'completed_at' => now()->toIso8601String(),
            'duration_played' => 60, 'completed' => false, 'error_code' => 'LIVE_STREAM_FAILED', 'metadata' => $metadata,
        ]]])->assertOk()->assertJsonPath('ingested', 1);
        $this->assertSame($metadata, PlaybackEvent::sole()->metadata);
    }

    public function test_detects_and_normalizes_all_supported_providers(): void
    {
        config(['live.hls_hosts' => ['video.example.com']]);
        foreach ([
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&other=1', 'youtube', 'dQw4w9WgXcQ'],
            ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            ['https://www.youtube.com/live/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            ['https://www.twitch.tv/Example', 'twitch', 'example'],
            ['https://kick.com/Example', 'kick', 'example'],
            ['https://video.example.com/live.m3u8?token=abc', 'hls', null],
        ] as [$url, $provider, $id]) {
            $source = app(LiveSourceParser::class)->parse($url);
            $this->assertSame($provider, $source['provider']);
            if ($id) {
                $this->assertSame($id, $source['source_id']);
            }
        }
    }

    public function test_rejects_unapproved_urls_including_host_confusion_and_injection(): void
    {
        foreach (['http://kick.com/test', 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ', 'https://user:pass@kick.com/test',
            'https://kick.com/test/extra', 'https://www.youtube.com/watch?v[]=abc', 'https://example.com/live.m3u8',
            'https://127.0.0.1/live.m3u8', 'javascript:alert(1)', 'https://kick.com/%22%3E', 'https://twitch.tv/directory',
            'https://kick.com:444/test', 'https://www.youtube.com/@channel/live'] as $url) {
            try {
                app(LiveSourceParser::class)->parse($url);
                $this->fail($url);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('url', $e->errors());
            }
        }
    }

    public function test_source_creation_detection_authorization_and_signed_embed(): void
    {
        $this->actingAs($this->superAdmin());
        $this->postJson('/admin/live-streams/detect', ['url' => 'https://kick.com/example'])->assertOk()->assertJsonPath('source.provider', 'kick');
        $response = $this->postJson('/admin/live-streams', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'name' => 'Evento']);
        $response->assertCreated()->assertJsonPath('media.type.value', 'live_stream');
        $asset = MediaAsset::sole();
        $this->assertSame('', $asset->storage_path);
        $url = $response->json('media.live.embed_url');
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertSee('onYouTubeIframeAPIReady', false);
        $this->get($url.'&extra=1')->assertForbidden();
        $this->get('/live/embed/'.$asset->id)->assertForbidden();
        $this->actingAs($this->businessUser(Business::factory()->create()));
        $this->postJson('/admin/live-streams', ['url' => 'https://kick.com/test'])->assertForbidden();
        $this->postJson('/admin/live-streams/detect', ['url' => 'https://kick.com/test'])->assertForbidden();
    }

    public function test_manifest_carries_live_configuration_and_downloadable_fallback_for_future_campaign(): void
    {
        $this->actingAs($this->superAdmin())->postJson('/admin/live-streams', ['url' => 'https://kick.com/example'])->assertCreated();
        $live = MediaAsset::sole();
        $fallback = MediaAsset::factory()->image()->create();
        $device = Device::factory()->online()->create(['current_layout_id' => Layout::create(['name' => '70/30', 'orientation' => 'landscape', 'business_percentage' => 70, 'advertising_percentage' => 30])->id]);
        $campaign = Campaign::factory()->create(['status' => 'scheduled', 'starts_at' => today()->addDay(), 'ends_at' => today()->addDays(2)]);
        $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id]);
        $configuration = ['display_mode' => 'fullscreen', 'audio' => true, 'starts_at' => today()->addDay()->toIso8601String(), 'ends_at' => today()->addDay()->addHour()->toIso8601String(), 'fallback_media_id' => $fallback->id, 'size_acknowledged' => true];
        $campaign->creatives()->create(['media_asset_id' => $live->id, 'duration' => 10, 'weight' => 1, 'position' => 0, 'status' => 'active', 'configuration' => $configuration]);
        $manifest = app(BuildDeviceManifest::class)->handle($device)->payload;
        $this->assertSame($configuration, $manifest['advertising_playlist']['campaigns'][0]['creatives'][0]['live_configuration']);
        $this->assertEqualsCanonicalizing([$live->id, $fallback->id], array_column($manifest['assets'], 'id'));
        $this->assertSame('kick', collect($manifest['assets'])->firstWhere('id', $live->id)['live']['provider']);
        $campaign->update(['status' => 'paused']);
        $this->assertEmpty(app(BuildDeviceManifest::class)->handle($device)->payload['advertising_playlist']['campaigns']);
    }
}
