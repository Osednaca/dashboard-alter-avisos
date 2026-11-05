<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\ManifestStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DevicePlaybackState;
use App\Domain\Devices\Services\DevicePlaybackStateService;
use App\Domain\Devices\Services\DevicePreviewService;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playlists\Models\Playlist;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DevicePreviewTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function payload(int $rotation = 90, string $split = 'top_bottom', string $area = 'bottom'): array
    {
        return [
            'device' => ['timezone' => 'America/Bogota'],
            'layout' => ['id' => 5, 'name' => 'Diseño confirmado', 'orientation' => 'portrait',
                'business_percentage' => 70, 'advertising_percentage' => 30,
                'configuration' => ['rotation' => $rotation, 'split' => $split, 'business_area' => $area]],
            'business_playlist' => ['id' => 10, 'name' => 'Lista confirmada', 'items' => [
                ['id' => 1, 'media_asset_id' => 1, 'order' => 2], ['id' => 2, 'media_asset_id' => 2, 'order' => 1],
            ]],
            'advertising_playlist' => ['campaigns' => [
                ['id' => 3, 'name' => 'Campaña futura', 'starts_on' => '2099-01-01', 'creatives' => [['media_asset_id' => 1]]],
                ['id' => 4, 'name' => 'Campaña confirmada', 'creatives' => [['media_asset_id' => 3]]],
            ]],
            'assets' => [
                ['id' => 1, 'type' => 'image', 'url' => '/confirmed.jpg', 'mime_type' => 'image/jpeg'],
                ['id' => 2, 'type' => 'video', 'url' => '/confirmed.mp4', 'mime_type' => 'video/mp4'],
                ['id' => 3, 'type' => 'live_stream', 'url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'mime_type' => 'application/x-live-stream',
                    'live' => ['provider' => 'youtube', 'original_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'source_id' => 'abcdefghijk', 'embed_url' => 'https://signage.test/live/embed/3?signature=test']],
            ],
        ];
    }

    private function confirm(Device $device, array $payload): void
    {
        // Delivered fixture assets are persisted public/admin media. Private
        // business-owned sources are created explicitly by ownership regressions.
        foreach ($payload['assets'] ?? [] as $asset) {
            if (! MediaAsset::query()->find($asset['id'])) {
                MediaAsset::factory()->create(['id' => $asset['id'], 'type' => $asset['type'],
                    'mime_type' => $asset['mime_type'], 'owner_type' => null, 'owner_id' => null]);
            }
        }
        $device->manifests()->create(['version' => '100', 'checksum' => hash('sha256', json_encode($payload)),
            'payload' => $payload, 'status' => ManifestStatus::Current, 'generated_at' => now()]);
        $device->forceFill(['current_manifest_version' => '100'])->save();
    }

    public function test_preview_uses_the_device_confirmed_snapshot_not_pending_or_current_database_assignments(): void
    {
        $device = Device::factory()->create();
        $this->confirm($device, $this->payload());
        $device->manifests()->where('version', '100')->update(['status' => ManifestStatus::Superseded]);
        $pending = $this->payload(0, 'side_by_side', 'left');
        $pending['assets'][1]['url'] = '/not-confirmed.mp4';
        $device->manifests()->create(['version' => '200', 'checksum' => 'new', 'payload' => $pending,
            'status' => ManifestStatus::Pending, 'generated_at' => now()]);
        $device->forceFill(['pending_manifest_version' => '200'])->save();
        MediaAsset::factory()->create();

        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('100', $preview['manifest_version']);
        $this->assertSame('200', $preview['pending_manifest_version']);
        $this->assertSame('/confirmed.mp4', $preview['business_media']['url']);
        $this->assertSame('Campaña confirmada', $preview['advertising']['campaign_name']);
        $this->assertSame('live_stream', $preview['advertising']['media']['type']);
        $this->assertSame(90, $preview['layout']['rotation']);
        $this->assertSame('top_bottom', $preview['layout']['split']);
        $this->assertFalse($preview['layout']['business_first']);
        $this->assertSame('approximate', $preview['status']);
        $this->assertFalse($preview['playback_reported']);
    }

    public function test_unconfirmed_preview_does_not_invent_content_from_recent_uploads_or_another_devices_manifest(): void
    {
        $device = Device::factory()->create();
        $other = Device::factory()->create();
        $this->confirm($other, $this->payload());
        $device->forceFill(['current_manifest_version' => '100'])->save();
        MediaAsset::factory()->create();

        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('unconfirmed', $preview['status']);
        $this->assertNull($preview['business_media']);
        $this->assertNull($preview['advertising']);
        $this->assertNull($preview['layout']);
        $this->assertNull($preview['manifest_version']);
    }

    public function test_snapshot_schedules_and_campaign_windows_respect_the_device_timezone_and_overnight_ranges(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 4)->setTime(4, 30)); // Saturday 23:30 in Bogotá.
        $device = Device::factory()->create();
        $payload = $this->payload();
        $payload['schedules'] = [['playlist_id' => 11, 'priority' => 10, 'days_of_week' => [6], 'daily_start_time' => '22:00', 'daily_end_time' => '02:00']];
        $payload['scheduled_playlists'] = [['id' => 11, 'name' => 'Noche', 'items' => [['media_asset_id' => 1, 'order' => 0]]]];
        $payload['advertising_playlist']['campaigns'][1]['days_of_week'] = [7];
        $this->confirm($device, $payload);

        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('Noche', $preview['playlist']['name']);
        $this->assertSame('/confirmed.jpg', $preview['business_media']['url']);
        $this->assertNull($preview['advertising']);
    }

    public function test_future_live_creative_is_not_shown_as_current_content(): void
    {
        $device = Device::factory()->create();
        $payload = $this->payload();
        $payload['advertising_playlist']['campaigns'][1]['creatives'][0]['live_configuration'] = ['starts_at' => '2099-01-01T00:00:00Z'];
        $this->confirm($device, $payload);
        $this->assertNull(app(DevicePreviewService::class)->forDevice($device)['advertising']);
    }

    public function test_explicit_zero_and_all_rotations_and_zone_order_are_preserved(): void
    {
        $device = Device::factory()->create();
        foreach ([0, 90, 180, 270] as $rotation) {
            $this->confirm($device, $this->payload($rotation, 'side_by_side', 'right'));
            $layout = app(DevicePreviewService::class)->forDevice($device)['layout'];
            $this->assertSame($rotation, $layout['rotation']);
            $this->assertSame('side_by_side', $layout['split']);
            $this->assertFalse($layout['business_first']);
            $device->manifests()->delete();
        }
    }

    public function test_preview_gets_never_rebuild_manifests_or_change_device_pointers(): void
    {
        $business = Business::factory()->create();
        $user = $this->businessUser($business);
        $device = Device::factory()->create(['business_id' => $business->id]);
        $this->confirm($device, $this->payload());
        $device->forceFill(['manifest_dirty' => true])->save();
        $before = $device->fresh()->getAttributes();
        foreach (['/business/dashboard', '/business/preview', "/business/screens/{$device->id}"] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertInertia(fn ($page) => $page->where('preview.manifest_version', '100')->where('preview.layout.rotation', 90));
        }
        $this->assertDatabaseCount('device_manifests', 1);
        $this->assertSame($before, $device->fresh()->getAttributes());
    }

    public function test_admin_selection_is_stable_and_partial_poll_only_reads_preview(): void
    {
        $first = Device::factory()->create(['status' => 'offline']);
        $second = Device::factory()->create(['status' => 'online']);
        $this->confirm($first, $this->payload());
        $this->confirm($second, $this->payload(270));
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('screenPreview.device.id', $first->id));
        $this->actingAs($admin)->get('/admin/dashboard?device='.$second->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('screenPreview.device.id', $second->id)->where('screenPreview.layout.rotation', 270));
        $this->actingAs($admin)->get('/admin/dashboard?device='.$second->id, [
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Admin/Dashboard', 'X-Inertia-Partial-Data' => 'screenPreview',
        ])->assertOk()->assertJsonPath('props.screenPreview.device.id', $second->id)->assertJsonMissingPath('props.overview');
        $this->assertDatabaseCount('device_manifests', 2);
    }

    public function test_business_selection_and_partial_refresh_cannot_leak_another_business_content(): void
    {
        $business = Business::factory()->create();
        $own = Device::factory()->create(['business_id' => $business->id]);
        $other = Device::factory()->create();
        $this->confirm($own, $this->payload());
        $this->confirm($other, $this->payload(0));
        $user = $this->businessUser($business);
        foreach (['/business/dashboard', '/business/preview'] as $url) {
            $this->actingAs($user)->get($url.'?device='.$other->id)->assertOk()
                ->assertInertia(fn ($page) => $page->where('preview.device.id', $own->id)->has('devices', 1));
        }
        $this->actingAs($user)->get('/business/preview?device='.$own->id, [
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Business/Preview/Index', 'X-Inertia-Partial-Data' => 'preview',
        ])->assertOk()->assertJsonPath('props.preview.device.id', $own->id)->assertJsonMissingPath('props.devices');
    }

    private function reportedState(array $overrides = []): array
    {
        return array_replace([
            'fresh' => true, 'received_at' => now()->toIso8601String(), 'age_ms' => 1000,
            'session_id' => 'fixture', 'sequence' => 2, 'scene' => 'playback',
            'layout' => ['manifest_version' => '99', 'rotation' => 270, 'split' => 'side_by_side',
                'business_percentage' => 60, 'business_first' => true, 'width_px' => 1080, 'height_px' => 1920],
            'zones' => ['business' => ['source' => 'manifest', 'state' => 'paused', 'manifest_version' => '99',
                'position_ms' => 4321, 'media' => ['id' => 42, 'type' => 'video', 'url' => '/actual-item.mp4']]],
        ], $overrides);
    }

    public function test_actual_report_replaces_inferred_content_layout_and_manifest_without_writes(): void
    {
        $device = Device::factory()->create();
        $this->confirm($device, $this->payload());
        $before = $device->fresh()->getAttributes();
        $this->mock(DevicePlaybackStateService::class)->shouldReceive('preview')->once()
            ->withArgs(fn (Device $candidate) => $candidate->id === $device->id)
            ->andReturn($this->reportedState());

        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('reported', $preview['status']);
        $this->assertTrue($preview['playback_reported']);
        $this->assertSame('/actual-item.mp4', $preview['business_media']['url']);
        $this->assertNull($preview['advertising']);
        $this->assertNull($preview['playlist']);
        $this->assertSame('99', $preview['manifest_version']);
        $this->assertSame(270, $preview['layout']['rotation']);
        $this->assertSame('60/40', $preview['layout']['ratio']);
        $this->assertSame(1080, $preview['layout']['width_px']);
        $this->assertSame(4321, $preview['playback']['zones']['business']['position_ms']);
        $this->assertSame($before, $device->fresh()->getAttributes());
        $this->assertDatabaseCount('device_manifests', 1);
    }

    public function test_stale_and_non_playback_reports_never_show_estimated_playlist_content(): void
    {
        $device = Device::factory()->create();
        $this->confirm($device, $this->payload());
        $service = $this->mock(DevicePlaybackStateService::class);
        foreach (['settings', 'pin', 'background', 'activation', 'playback'] as $scene) {
            $fresh = $scene !== 'playback';
            $service->shouldReceive('preview')->once()->andReturn($this->reportedState([
                'fresh' => $fresh, 'scene' => $scene, 'zones' => [], 'age_ms' => $fresh ? 0 : 16000,
            ]));
            $preview = app(DevicePreviewService::class)->forDevice($device);
            $this->assertSame($fresh ? 'reported' : 'stale', $preview['status']);
            $this->assertNull($preview['business_media']);
            $this->assertNull($preview['advertising']);
            $this->assertSame([], $preview['playback']['zones']);
        }
    }

    public function test_business_preview_polls_real_validated_state_then_hides_it_after_fifteen_seconds(): void
    {
        $business = Business::factory()->create();
        $device = Device::factory()->online()->create(['business_id' => $business->id]);
        $video = MediaAsset::factory()->video()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $payload = $this->payload();
        $payload['business_playlist']['items'] = [['id' => 20, 'media_asset_id' => $video->id, 'order' => 1]];
        $payload['assets'] = [['id' => $video->id, 'type' => 'video', 'url' => '/actual-video.mp4', 'mime_type' => 'video/mp4']];
        $payload['advertising_playlist'] = ['campaigns' => []];
        $this->confirm($device, $payload);
        app(DevicePlaybackStateService::class)->accept($device, [
            'schema_version' => 1, 'session_id' => '97b13501-cd75-44bc-b11b-55b8a0effc98', 'sequence' => 1, 'sample_age_ms' => 0,
            'scene' => 'playback', 'layout' => array_replace($this->reportedState()['layout'], ['manifest_version' => '100']),
            'zones' => ['business' => ['source' => 'manifest', 'state' => 'paused', 'manifest_version' => '100',
                'item_id' => 'business-20', 'media_asset_id' => $video->id, 'position_ms' => 4321, 'duration_ms' => 10000]],
        ]);
        $before = $device->fresh()->getAttributes();
        $user = $this->businessUser($business);
        $this->actingAs($user)->get('/business/preview')->assertOk()->assertInertia(fn ($page) => $page
            ->where('preview.status', 'reported')->where('preview.playback.zones.business.position_ms', 4321)
            ->where('preview.business_media.url', '/actual-video.mp4'));
        $this->travel(16)->seconds();
        $this->actingAs($user)->get('/business/preview')->assertOk()->assertInertia(fn ($page) => $page
            ->where('preview.status', 'stale')->where('preview.business_media', null)->has('preview.playback.zones', 0));
        $this->assertSame($before, $device->fresh()->getAttributes());
        $this->assertDatabaseCount('device_playback_states', 1);
    }

    public function test_legacy_fallback_accepts_numeric_string_business_identity_but_rejects_a_different_business(): void
    {
        $device = Device::factory()->create();
        $this->confirm($device, $this->payload());
        DevicePlaybackState::query()->create([
            'device_id' => $device->id, 'business_id' => $device->business_id,
            'session_id' => '97b13501-cd75-44bc-b11b-55b8a0effc98', 'sequence' => 1,
            'payload' => [], 'received_at' => now(),
        ]);
        $this->mock(DevicePlaybackStateService::class)->shouldReceive('preview')->twice()->andReturnNull();
        $device->setAttribute('business_id', (string) $device->business_id);

        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('approximate', $preview['status']);
        $this->assertSame('/confirmed.mp4', $preview['business_media']['url']);
        $this->assertSame('Lista confirmada', $preview['playlist']['name']);

        $device->setAttribute('business_id', (string) Business::factory()->create()->id);
        $preview = app(DevicePreviewService::class)->forDevice($device);

        $this->assertSame('unconfirmed', $preview['status']);
        $this->assertNull($preview['business_media']);
        $this->assertNull($preview['playlist']);
        $this->assertNull($preview['manifest_version']);
    }

    public function test_reassignment_or_deleted_sources_cannot_leak_prior_business_urls_or_playlist_names(): void
    {
        $device = Device::factory()->online()->create();
        $oldPlaylist = Playlist::query()->create(['business_id' => $device->business_id, 'name' => 'Private old business playlist', 'type' => 'business', 'status' => 'active']);
        $device->forceFill(['current_playlist_id' => $oldPlaylist->id])->save();
        $media = MediaAsset::factory()->video()->create(['owner_type' => (new Business)->getMorphClass(), 'owner_id' => $device->business_id]);
        $payload = $this->payload();
        $payload['assets'] = [['id' => $media->id, 'type' => 'video', 'url' => '/old-private.mp4', 'mime_type' => 'video/mp4']];
        $payload['business_playlist']['name'] = 'Private old business playlist';
        $payload['business_playlist']['id'] = $oldPlaylist->id;
        $payload['business_playlist']['items'] = [['id' => 20, 'media_asset_id' => $media->id, 'order' => 1]];
        $this->confirm($device, $payload);
        $this->assertSame('/old-private.mp4', app(DevicePreviewService::class)->forDevice($device)['business_media']['url']);
        $newBusiness = Business::factory()->create();
        $device->forceFill(['business_id' => $newBusiness->id])->save();
        foreach ([false, true] as $deleted) {
            if ($deleted) {
                $media->delete();
            }
            $preview = app(DevicePreviewService::class)->forDevice($device->fresh());
            $this->assertSame('unconfirmed', $preview['status']);
            $this->assertNull($preview['business_media']);
            $this->assertNull($preview['advertising']);
            $this->assertNull($preview['playlist']);
            $this->assertNull($preview['layout']);
            $this->assertNull($preview['device']['current_playlist']);
            $this->assertStringNotContainsString('/old-private.mp4', json_encode($preview));
            $this->assertStringNotContainsString('Private old business playlist', json_encode($preview));
        }
    }
}
