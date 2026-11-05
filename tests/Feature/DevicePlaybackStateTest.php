<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DevicePlaybackState;
use App\Domain\Devices\Services\DevicePlaybackStateService;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DevicePlaybackStateTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function fixture(): array
    {
        $device = Device::factory()->online()->create(['current_manifest_version' => '100']);
        $media = MediaAsset::factory()->video()->create(['owner_type' => (new Business)->getMorphClass(), 'owner_id' => $device->business_id]);
        $payload = [
            'layout' => [],
            'business_playlist' => ['id' => 10, 'items' => [['id' => 20, 'media_asset_id' => $media->id]]],
            'assets' => [$this->asset($media)],
        ];
        $device->manifests()->create(['version' => '100', 'status' => 'current', 'payload' => $payload, 'generated_at' => now(), 'activated_at' => now()]);

        return [$device, $media, $payload];
    }

    private function asset(MediaAsset $media): array
    {
        return ['id' => $media->id, 'type' => $media->type->value, 'url' => '/delivered-'.$media->id.'.mp4', 'mime_type' => $media->mime_type];
    }

    private function report(MediaAsset $media): array
    {
        return ['schema_version' => 1, 'session_id' => (string) Str::uuid(), 'sequence' => 1, 'sample_age_ms' => 0,
            'scene' => 'playback', 'layout' => ['manifest_version' => '100', 'rotation' => 90, 'split' => 'top_bottom',
                'business_percentage' => 70, 'business_first' => false, 'width_px' => 1080, 'height_px' => 1920],
            'zones' => ['business' => ['source' => 'manifest', 'manifest_version' => '100', 'item_id' => 'business-20',
                'media_asset_id' => $media->id, 'state' => 'playing', 'position_ms' => 25000, 'duration_ms' => 45000]]];
    }

    private function send(Device $device, array $report)
    {
        return $this->withToken($device->issueToken())->postJson('/api/v1/device/playback-state', $report);
    }

    public function test_current_report_is_resolved_from_delivered_source_and_replaces_one_row_without_heartbeat_growth(): void
    {
        [$device, $media] = $this->fixture();
        $report = $this->report($media);
        $this->send($device, $report)->assertOk()->assertJsonPath('accepted', true);
        $report['sequence']++;
        $report['zones']['business']['position_ms'] = 26000;
        $this->send($device, $report)->assertOk();
        $this->assertDatabaseCount('device_playback_states', 1);
        $this->assertDatabaseCount('device_heartbeats', 0);
        $preview = app(DevicePlaybackStateService::class)->preview($device->fresh());
        $this->assertTrue($preview['fresh']);
        $this->assertSame(26000, $preview['zones']['business']['position_ms']);
        $this->assertSame('/delivered-'.$media->id.'.mp4', $preview['zones']['business']['media']['url']);
        $this->assertSame(1080, $preview['layout']['width_px']);
        $this->assertSame(2, $preview['sequence']);
        $this->assertArrayNotHasKey('url', DevicePlaybackState::query()->sole()->payload['zones']['business']);
    }

    public function test_absent_revoked_expired_tokens_and_disabled_devices_cannot_report(): void
    {
        [$device, $media] = $this->fixture();
        $report = $this->report($media);
        $this->postJson('/api/v1/device/playback-state', $report)->assertUnauthorized();
        $token = $device->issueToken();
        $device->forceFill(['token_revoked_at' => now()])->save();
        $this->withToken($token)->postJson('/api/v1/device/playback-state', $report)->assertUnauthorized();
        $device->forceFill(['token_revoked_at' => null, 'token_expires_at' => now()->subSecond()])->save();
        $this->withToken($token)->postJson('/api/v1/device/playback-state', $report)->assertUnauthorized();
        $device->forceFill(['status' => 'disabled'])->save();
        $this->send($device, $report)->assertForbidden();
        $this->assertDatabaseCount('device_playback_states', 0);
        $this->assertSame('disabled', $device->fresh()->status->value);
    }

    public function test_unknown_keys_url_injection_invalid_bounds_and_ambiguous_visible_zones_are_rejected(): void
    {
        [$device, $media] = $this->fixture();
        $report = $this->report($media);
        foreach ([['device_id', $device->id], ['sample_age_ms', 15001], ['sequence', -1], ['schema_version', 2],
            ['layout.width_px', 0], ['layout.rotation', 45], ['layout.url', 'https://private.invalid'],
            ['zones.business.url', 'https://private.invalid'], ['zones.business.position_ms', -1],
            ['zones.business.state', 'unknown'], ['zones.foreign', ['source' => 'empty', 'state' => 'empty']]] as [$key, $value]) {
            $bad = $report;
            data_set($bad, $key, $value);
            $this->send($device, $bad)->assertUnprocessable();
        }
        $bad = $report;
        $bad['zones']['fullscreen'] = ['source' => 'empty', 'state' => 'empty'];
        $this->send($device, $bad)->assertUnprocessable();
        $this->assertDatabaseCount('device_playback_states', 0);
    }

    public function test_cross_device_pending_manifests_wrong_item_and_unassigned_assets_do_not_authorize_media(): void
    {
        [$device, $media, $payload] = $this->fixture();
        $other = Device::factory()->online()->create(['current_manifest_version' => '200']);
        $other->manifests()->create(['version' => '200', 'status' => 'current', 'payload' => $payload, 'generated_at' => now(), 'activated_at' => now()]);
        $device->manifests()->create(['version' => '300', 'status' => 'pending', 'payload' => $payload, 'generated_at' => now()]);
        foreach (['200', '300'] as $version) {
            $report = $this->report($media);
            $report['zones']['business']['manifest_version'] = $version;
            $this->send($device, $report)->assertUnprocessable();
        }
        $report = $this->report($media);
        $report['zones']['business']['item_id'] = 'business-999';
        $this->send($device, $report)->assertUnprocessable();
        $report = $this->report($media);
        $report['zones']['business']['media_asset_id'] = MediaAsset::factory()->create()->id;
        $this->send($device, $report)->assertUnprocessable();
        $this->assertDatabaseCount('device_playback_states', 0);
    }

    public function test_old_activated_manifest_can_report_held_cursor_but_unactivated_superseded_version_cannot(): void
    {
        [$device, $media, $payload] = $this->fixture();
        $device->manifests()->where('version', '100')->update(['status' => 'superseded']);
        $device->manifests()->create(['version' => '200', 'status' => 'current', 'payload' => $payload, 'generated_at' => now(), 'activated_at' => now()]);
        $device->forceFill(['current_manifest_version' => '200'])->save();
        $report = $this->report($media);
        $report['layout']['manifest_version'] = '200';
        $this->send($device, $report)->assertOk();
        $device->manifests()->where('version', '100')->update(['activated_at' => null]);
        $report['sequence']++;
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_duplicate_and_older_sequences_do_not_renew_freshness_or_mutate_gets(): void
    {
        [$device, $media] = $this->fixture();
        $this->freezeTime();
        $report = $this->report($media);
        $report['sequence'] = 2;
        $this->send($device, $report)->assertOk();
        $before = DevicePlaybackState::query()->sole()->getAttributes();
        $lastSeen = $device->fresh()->last_seen_at->toIso8601String();
        $this->travel(16)->seconds();
        $this->send($device, $report)->assertOk()->assertJsonPath('accepted', false);
        $report['sequence'] = 1;
        $this->send($device, $report)->assertOk()->assertJsonPath('accepted', false);
        $preview = app(DevicePlaybackStateService::class)->preview($device->fresh());
        $this->assertFalse($preview['fresh']);
        $this->assertGreaterThanOrEqual(16000, $preview['age_ms']);
        $this->assertSame([], $preview['zones']);
        $this->assertSame($before, DevicePlaybackState::query()->sole()->getAttributes());
        $this->assertSame($lastSeen, $device->fresh()->last_seen_at->toIso8601String());
    }

    public function test_freshness_accounts_for_sample_age_offline_state_and_business_reassignment(): void
    {
        [$device, $media] = $this->fixture();
        $this->freezeTime();
        $report = $this->report($media);
        $report['sample_age_ms'] = 14000;
        $this->send($device, $report)->assertOk();
        $this->travel(2)->seconds();
        $this->assertFalse(app(DevicePlaybackStateService::class)->preview($device->fresh())['fresh']);
        $device->forceFill(['status' => 'offline'])->save();
        $this->assertSame([], app(DevicePlaybackStateService::class)->preview($device)['zones']);
        $device->forceFill(['business_id' => Business::factory()->create()->id, 'status' => 'online'])->save();
        $this->assertNull(app(DevicePlaybackStateService::class)->preview($device));
        $report['sequence']++;
        $report['sample_age_ms'] = 0;
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_non_playback_scenes_and_empty_surfaces_carry_no_media_and_hide_underlying_zones(): void
    {
        [$device, $media] = $this->fixture();
        foreach (['settings', 'pin', 'background', 'activation'] as $scene) {
            $report = $this->report($media);
            $report['scene'] = $scene;
            $this->send($device, $report)->assertUnprocessable();
            $report['zones'] = [];
            $this->send($device, $report)->assertOk();
            $preview = app(DevicePlaybackStateService::class)->preview($device->fresh());
            $this->assertSame($scene, $preview['scene']);
            $this->assertSame([], $preview['zones']);
        }
        $report = $this->report($media);
        $report['zones'] = ['business' => ['source' => 'empty', 'state' => 'empty']];
        $this->send($device, $report)->assertOk();
        $this->assertNull(app(DevicePlaybackStateService::class)->preview($device->fresh())['zones']['business']['media']);
        $report['zones']['business']['media_asset_id'] = $media->id;
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_quick_play_requires_the_exact_active_delivery_command_media_and_zone(): void
    {
        [$device, $media] = $this->fixture();
        $play = app(StartQuickPlay::class)->handle($this->superAdmin(), $media, QuickPlayDisplayMode::Fullscreen, QuickPlayScope::Devices, 30, ['device_ids' => [$device->id]]);
        $delivery = $play->devices()->sole();
        $report = $this->report($media);
        $report['zones'] = ['fullscreen' => ['source' => 'quick_play', 'quick_play_device_id' => $delivery->id,
            'command_id' => $delivery->command_id, 'media_asset_id' => $media->id, 'state' => 'playing', 'position_ms' => 1200]];
        $this->send($device, $report)->assertOk();
        $this->assertSame($media->url, app(DevicePlaybackStateService::class)->preview($device->fresh())['zones']['fullscreen']['media']['url']);
        $numericString = $report;
        $numericString['sequence']++;
        $numericString['zones']['fullscreen']['command_id'] = (string) $delivery->command_id;
        $this->send($device, $numericString)->assertOk()->assertJsonPath('accepted', true);
        foreach (['command_id', 'media_asset_id', 'quick_play_device_id'] as $key) {
            $bad = $report;
            $bad['zones']['fullscreen'][$key] += 999;
            $this->send($device, $bad)->assertUnprocessable();
        }
        $bad = $report;
        $bad['zones'] = ['business' => $report['zones']['fullscreen']];
        $this->send($device, $bad)->assertUnprocessable();
        $delivery->forceFill(['status' => 'completed'])->save();
        $this->send($device, $report)->assertUnprocessable();
        $this->assertFalse(app(DevicePlaybackStateService::class)->preview($device->fresh())['fresh']);
    }

    public function test_live_and_actual_fallback_require_the_creative_source_and_its_declared_display_mode(): void
    {
        [$device, $media, $payload] = $this->fixture();
        $live = MediaAsset::factory()->create(['type' => 'live_stream', 'mime_type' => 'application/x-live-stream',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=abcdefghijk']]]);
        $payload['assets'][] = $this->asset($live) + ['live' => ['provider' => 'youtube', 'original_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'source_id' => 'abcdefghijk', 'embed_url' => '/live/embed/'.$live->id.'?signature=server']];
        $payload['advertising_playlist'] = ['campaigns' => [['name' => 'Directo', 'creatives' => [['creative_id' => 77,
            'media_asset_id' => $live->id, 'live_configuration' => ['display_mode' => 'fullscreen', 'fallback_media_id' => $media->id]]]]]];
        $device->manifests()->where('version', '100')->update(['payload' => $payload]);
        $report = $this->report($media);
        $report['zones'] = ['fullscreen' => ['source' => 'live', 'manifest_version' => '100', 'live_creative_id' => 77,
            'media_asset_id' => $live->id, 'state' => 'playing', 'live_state' => 'unverified']];
        $this->send($device, $report)->assertOk();
        $this->assertSame('youtube', app(DevicePlaybackStateService::class)->preview($device->fresh())['zones']['fullscreen']['media']['live']['provider']);
        $report['zones']['fullscreen']['source'] = 'live_fallback';
        $report['sequence']++;
        $report['zones']['fullscreen']['media_asset_id'] = $media->id;
        $this->send($device, $report)->assertOk();
        $report['zones']['fullscreen']['live_creative_id'] = 999;
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_private_asset_reassignment_is_revalidated_on_get_without_replaying_a_url(): void
    {
        [$device, $media] = $this->fixture();
        $this->send($device, $this->report($media))->assertOk();
        $media->forceFill(['owner_id' => Business::factory()->create()->id])->save();
        $preview = app(DevicePlaybackStateService::class)->preview($device->fresh());
        $this->assertFalse($preview['fresh']);
        $this->assertSame([], $preview['zones']);
    }

    public function test_quick_delivery_cannot_cross_devices_and_expiration_or_deletion_masks_an_accepted_source(): void
    {
        [$device, $media] = $this->fixture();
        $play = app(StartQuickPlay::class)->handle($this->superAdmin(), $media, QuickPlayDisplayMode::Fullscreen, QuickPlayScope::Devices, 30, ['device_ids' => [$device->id]]);
        $delivery = $play->devices()->sole();
        $report = $this->report($media);
        $report['zones'] = ['fullscreen' => ['source' => 'quick_play', 'quick_play_device_id' => $delivery->id,
            'command_id' => $delivery->command_id, 'media_asset_id' => $media->id, 'state' => 'paused']];
        $other = Device::factory()->online()->create(['business_id' => $device->business_id]);
        $foreign = $report;
        $foreign['layout']['manifest_version'] = null;
        $this->send($other, $foreign)->assertUnprocessable();
        $this->send($device, $report)->assertOk();
        $play->forceFill(['expires_at' => now()->subSecond()])->save();
        $report['sequence']++;
        $this->send($device, $report)->assertUnprocessable();
        $this->assertSame([], app(DevicePlaybackStateService::class)->preview($device->fresh())['zones']);
        $play->forceFill(['expires_at' => now()->addMinute()])->save();
        $play->delete();
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_live_fallback_can_use_a_real_list_item_but_not_an_arbitrary_downloaded_asset(): void
    {
        [$device, $media, $payload] = $this->fixture();
        $live = MediaAsset::factory()->create(['type' => 'live_stream', 'mime_type' => 'application/x-live-stream',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=abcdefghijk']]]);
        $spare = MediaAsset::factory()->image()->create();
        $payload['assets'][] = $this->asset($live);
        $payload['assets'][] = $this->asset($spare);
        $payload['advertising_playlist'] = ['campaigns' => [['creatives' => [['creative_id' => 77,
            'media_asset_id' => $live->id, 'live_configuration' => ['display_mode' => 'advertising_zone']]]]]];
        $device->manifests()->where('version', '100')->update(['payload' => $payload]);
        $report = $this->report($media);
        $report['zones'] = ['advertising' => ['source' => 'live_fallback', 'manifest_version' => '100', 'live_creative_id' => 77,
            'item_id' => 'business-20', 'media_asset_id' => $media->id, 'state' => 'playing']];
        $this->send($device, $report)->assertOk();
        $report['sequence']++;
        $report['zones']['advertising']['media_asset_id'] = $spare->id;
        $this->send($device, $report)->assertUnprocessable();
        $report['zones']['advertising']['media_asset_id'] = $media->id;
        $report['zones']['advertising']['item_id'] = 'business-999';
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_scheduled_business_items_and_normal_advertising_items_require_their_own_zone_identity(): void
    {
        [$device, $media, $payload] = $this->fixture();
        $ad = MediaAsset::factory()->image()->create();
        $payload['assets'][] = $this->asset($ad);
        $payload['scheduled_playlists'] = [['id' => 11, 'items' => [['id' => 21, 'media_asset_id' => $media->id]]]];
        $payload['advertising_playlist'] = ['campaigns' => [['creatives' => [['creative_id' => 78, 'media_asset_id' => $ad->id]]]]];
        $device->manifests()->where('version', '100')->update(['payload' => $payload]);
        $report = $this->report($media);
        $report['zones']['business']['item_id'] = 'business-21';
        $report['zones']['advertising'] = ['source' => 'manifest', 'manifest_version' => '100',
            'item_id' => 'creative-78', 'media_asset_id' => $ad->id, 'state' => 'paused'];
        $this->send($device, $report)->assertOk();
        $report['sequence']++;
        $report['zones']['business'] = $report['zones']['advertising'];
        $this->send($device, $report)->assertUnprocessable();
    }

    public function test_scene_with_no_manifest_and_device_deletion_keep_storage_bounded(): void
    {
        [$device, $media] = $this->fixture();
        $report = $this->report($media);
        $report['scene'] = 'activation';
        $report['layout'] = null;
        $report['zones'] = [];
        $this->send($device, $report)->assertOk();
        $this->assertTrue(app(DevicePlaybackStateService::class)->preview($device->fresh())['fresh']);
        $device->delete();
        $this->assertDatabaseCount('device_playback_states', 0);
    }

    public function test_legacy_heartbeat_remains_optional_and_does_not_fabricate_a_playback_report(): void
    {
        [$device] = $this->fixture();
        $this->withToken($device->issueToken())->postJson('/api/v1/device/heartbeat', [])->assertOk();
        $this->assertDatabaseCount('device_playback_states', 0);
        $this->assertNull(app(DevicePlaybackStateService::class)->preview($device));
        $this->assertDatabaseCount('device_heartbeats', 1);
    }
}
