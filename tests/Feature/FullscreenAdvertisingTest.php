<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class FullscreenAdvertisingTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_manifest_uses_full_display_and_normalizes_legacy_live_configuration_without_mutating_it(): void
    {
        $layout = Layout::query()->create([
            'name' => 'Legacy', 'orientation' => 'portrait', 'business_percentage' => 70,
            'advertising_percentage' => 30, 'configuration' => ['audio_mode' => 'none', 'rotation' => 90],
        ]);
        $device = Device::factory()->online()->create(['current_layout_id' => $layout->id]);
        $live = MediaAsset::factory()->create([
            'type' => 'live_stream', 'storage_path' => '',
            'metadata' => ['live' => ['original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
        ]);
        $campaign = Campaign::factory()->create(['status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addDay()]);
        $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id]);
        $creative = $campaign->creatives()->create([
            'media_asset_id' => $live->id, 'duration' => 10, 'weight' => 1, 'position' => 0,
            'status' => 'active', 'configuration' => ['display_mode' => 'business_zone'],
        ]);
        $payload = app(BuildDeviceManifest::class)->handle($device)->payload;

        $this->assertSame(0, $payload['layout']['business_percentage']);
        $this->assertSame(100, $payload['layout']['advertising_percentage']);
        $this->assertSame('portrait', $payload['layout']['orientation']);
        $this->assertSame('none', $payload['layout']['configuration']['audio_mode']);
        $this->assertNull($payload['business_playlist']);
        $this->assertSame([], $payload['scheduled_playlists']);
        $this->assertSame([], $payload['schedules']);
        $this->assertSame('fullscreen', $payload['advertising_playlist']['campaigns'][0]['creatives'][0]['live_configuration']['display_mode']);
        $this->assertSame('business_zone', $creative->fresh()->configuration['display_mode']);
        $this->assertSame(70, $layout->fresh()->business_percentage);
    }

    public function test_old_split_manifest_is_rebuilt_once_on_device_poll(): void
    {
        $device = Device::factory()->online()->create();
        $old = app(BuildDeviceManifest::class)->handle($device);
        $payload = $old->payload;
        $payload['layout']['business_percentage'] = 70;
        $payload['layout']['advertising_percentage'] = 30;
        $old->update(['payload' => $payload]);
        $this->withToken($device->issueToken())->getJson('/api/v1/device/sync')->assertOk();
        $this->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.payload.layout.business_percentage', 0)
            ->assertJsonPath('manifest.payload.layout.advertising_percentage', 100);
        $this->assertSame(2, $device->manifests()->count());
        $this->getJson('/api/v1/device/sync')->assertOk();
        $this->assertSame(2, $device->manifests()->count());
    }

    public function test_quick_play_rejects_partial_modes_and_delivers_old_queued_commands_fullscreen(): void
    {
        $device = Device::factory()->online()->create();
        $media = MediaAsset::factory()->image()->create();
        $user = $this->superAdmin();
        $this->actingAs($user)->postJson('/admin/quick-play', [
            'media_asset_id' => $media->id, 'display_mode' => 'advertising',
            'scope' => 'devices', 'duration' => 10, 'device_ids' => [$device->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('display_mode');

        $play = app(StartQuickPlay::class)->handle($user, $media, QuickPlayDisplayMode::Advertising,
            QuickPlayScope::Devices, 10, ['device_ids' => [$device->id]]);
        $this->assertSame(QuickPlayDisplayMode::Fullscreen, $play->display_mode);
        $command = $play->devices()->sole()->command;
        $command->update(['payload' => array_replace($command->payload, ['display_mode' => 'business'])]);
        $this->withToken($device->issueToken())->getJson('/api/v1/device/commands')->assertOk()
            ->assertJsonPath('commands.0.payload.display_mode', 'fullscreen');
    }
}
