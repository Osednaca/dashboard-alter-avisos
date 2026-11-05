<?php

namespace Tests\Feature;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceManifest;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\LiveSourceParser;
use App\Domain\Media\Services\LiveSourcePayload;
use App\Domain\Playback\Models\PlaybackEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://signage.test']);
    }

    public function test_live_embed_signing_uses_canonical_https_origin_instead_of_ambient_url_root(): void
    {
        $asset = MediaAsset::factory()->create([
            'type' => 'live_stream',
            'storage_path' => '',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
        ]);
        URL::forceRootUrl('http://ambient.invalid');
        URL::forceScheme('http');

        try {
            $requestPayload = app(LiveSourcePayload::class)->forAsset($asset);
            $this->assertSame('http://ambient.invalid', URL::to('/'));
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }

        $queuePayload = app(LiveSourcePayload::class)->forAsset($asset);
        $this->assertSame($requestPayload['embed_url'], $queuePayload['embed_url']);
        $this->assertSame('https', parse_url($requestPayload['embed_url'], PHP_URL_SCHEME));
        $this->assertSame('signage.test', parse_url($requestPayload['embed_url'], PHP_URL_HOST));
        $this->get($requestPayload['embed_url'])->assertOk();
        $this->get($requestPayload['embed_url'].'&tampered=1')->assertForbidden();

        $twitch = MediaAsset::factory()->create([
            'type' => 'live_stream',
            'storage_path' => '',
            'metadata' => ['live' => ['original_url' => 'https://www.twitch.tv/examplechannel']],
        ]);
        $twitchPayload = app(LiveSourcePayload::class)->forAsset($twitch);
        foreach (['embed_url', 'embed_audio_url'] as $key) {
            $this->assertSame('https', parse_url($twitchPayload[$key], PHP_URL_SCHEME));
            $this->assertSame('signage.test', parse_url($twitchPayload[$key], PHP_URL_HOST));
        }
    }

    public function test_live_embed_signing_rejects_a_non_https_application_origin(): void
    {
        $asset = MediaAsset::factory()->create([
            'type' => 'live_stream',
            'storage_path' => '',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
        ]);
        config(['app.url' => 'http://signage.test']);

        $this->expectException(\LogicException::class);
        app(LiveSourcePayload::class)->forAsset($asset);
    }

    /** @return array{Device, MediaAsset, DeviceManifest} */
    private function createDeviceWithNoncanonicalCurrentLiveManifest(bool $supersedeCurrentStatus = false): array
    {
        $device = Device::factory()->online()->create();
        $asset = MediaAsset::factory()->create([
            'type' => 'live_stream',
            'storage_path' => '',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
        ]);
        $campaign = Campaign::factory()->create([
            'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addDay(),
            'daily_start_time' => '00:00:00', 'daily_end_time' => '23:59:59', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id]);
        $campaign->creatives()->create([
            'media_asset_id' => $asset->id, 'duration' => 10, 'weight' => 1, 'position' => 0,
            'status' => 'active', 'configuration' => [],
        ]);

        $oldManifest = app(BuildDeviceManifest::class)->handle($device);
        $payload = $oldManifest->payload;
        foreach ($payload['assets'] as &$entry) {
            if ($entry['id'] === $asset->id) {
                foreach (['embed_url', 'embed_audio_url'] as $key) {
                    $parts = parse_url($entry['live'][$key]);
                    $entry['live'][$key] = 'https://legacy.invalid'.$parts['path'].'?'.$parts['query'];
                }
            }
        }
        unset($entry);
        $oldManifest->forceFill(['payload' => $payload, 'checksum' => hash('sha256', json_encode($payload))])->save();

        $this->withToken($device->issueToken())
            ->postJson('/api/v1/device/sync/acknowledge', ['version' => $oldManifest->version, 'success' => true])
            ->assertOk();
        if ($supersedeCurrentStatus) {
            $oldManifest->forceFill(['status' => 'superseded'])->save();
        }

        return [$device, $asset, $oldManifest];
    }

    public function test_sync_repairs_cached_noncanonical_current_live_embed_once(): void
    {
        [$device, $asset, $oldManifest] = $this->createDeviceWithNoncanonicalCurrentLiveManifest();
        $this->withToken($device->issueToken());
        $this->assertSame('current', $oldManifest->fresh()->status->value);
        $oldLive = collect($oldManifest->fresh()->payload['assets'])->firstWhere('id', $asset->id)['live'];
        $this->assertSame('legacy.invalid', parse_url($oldLive['embed_url'], PHP_URL_HOST));

        $sync = $this->getJson('/api/v1/device/sync')->assertOk();
        $newVersion = $sync->json('pending_manifest_version');
        $this->assertGreaterThan((int) $oldManifest->version, (int) $newVersion);
        $sync->assertJsonPath('current_manifest_version', $oldManifest->version)
            ->assertJsonPath('update_available', true);
        $manifest = $this->getJson('/api/v1/device/manifest')->assertOk();
        $this->assertSame($newVersion, $manifest->json('manifest.version'));
        $live = collect($manifest->json('manifest.payload.assets'))->firstWhere('id', $asset->id)['live'];
        $this->assertSame('signage.test', parse_url($live['embed_url'], PHP_URL_HOST));

        $this->getJson('/api/v1/device/sync')->assertOk()
            ->assertJsonPath('pending_manifest_version', $newVersion);
        $this->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.version', $newVersion);
        $this->assertSame(2, $device->manifests()->count());
    }

    public function test_manifest_repair_prefers_good_pending_manifest_over_noncanonical_current_pointer(): void
    {
        [$device, , $oldManifest] = $this->createDeviceWithNoncanonicalCurrentLiveManifest(supersedeCurrentStatus: true);
        $this->withToken($device->issueToken());
        $this->assertSame('superseded', $oldManifest->fresh()->status->value);
        $this->assertNotNull($device->fresh()->current_manifest_version);

        $repaired = $this->getJson('/api/v1/device/manifest')->assertOk();
        $newVersion = $repaired->json('manifest.version');
        $this->assertGreaterThan((int) $oldManifest->version, (int) $newVersion);
        $this->assertSame('pending', $repaired->json('manifest.status'));
        $this->assertSame('signage.test', parse_url($repaired->json('manifest.payload.assets.0.live.embed_url'), PHP_URL_HOST));
        $this->getJson('/api/v1/device/sync')->assertOk()
            ->assertJsonPath('current_manifest_version', $oldManifest->version)
            ->assertJsonPath('pending_manifest_version', $newVersion);
        $this->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.version', $newVersion);
        $this->assertSame(2, $device->manifests()->count());
    }

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
        foreach ([['ends_at' => now()->subHour()->toIso8601String()], ['size_acknowledged' => false], ['fallback_media_id' => $live->id], ['display_mode' => 'anything'], ['display_mode' => 'advertising_zone'], ['display_mode' => 'business_zone']] as $patch) {
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
