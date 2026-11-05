<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DependentContentDeliveryTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function poll(Device $device): array
    {
        $this->withToken($device->issueToken());
        $this->getJson('/api/v1/device/sync')->assertOk();

        return $this->getJson('/api/v1/device/manifest')->assertOk()->json('manifest');
    }

    private function acknowledge(Device $device, array $manifest): void
    {
        $this->withToken($device->issueToken())->postJson('/api/v1/device/sync/acknowledge', [
            'version' => $manifest['version'], 'success' => true,
        ])->assertOk();
    }

    public function test_reenabled_screen_gets_campaign_edits_missed_while_disabled(): void
    {
        Bus::fake();
        $admin = $this->superAdmin();
        $device = Device::factory()->online()->create();
        $campaign = Campaign::factory()->create(['status' => 'active', 'starts_at' => today(), 'ends_at' => today()->addDays(3)]);
        $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id, 'is_exclusion' => false]);
        $media = MediaAsset::factory()->image()->create();
        $campaign->creatives()->create(['media_asset_id' => $media->id, 'duration' => 12, 'weight' => 1, 'position' => 0, 'status' => 'active']);
        app(BuildDeviceManifest::class)->handle($device);
        $original = $this->poll($device);
        $this->acknowledge($device, $original);
        $this->actingAs($admin)->post('/admin/devices/'.$device->id.'/toggle-status')->assertRedirect();
        $this->put('/admin/campaigns/'.$campaign->id, [
            'advertiser_id' => $campaign->advertiser_id, 'name' => $campaign->name,
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addDays(3)->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $media->id, 'duration' => 49, 'weight' => 1]],
            'targets' => [['target_type' => 'device', 'target_id' => $device->id, 'is_exclusion' => false]],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($device->fresh()->manifest_dirty);
        $this->post('/admin/devices/'.$device->id.'/toggle-status')->assertRedirect();
        $latest = $this->poll($device);
        $this->assertSame(49, $latest['payload']['advertising_playlist']['campaigns'][0]['creatives'][0]['duration']);
        $this->assertNotSame($original['checksum'], $latest['checksum']);
    }

    public function test_rebuilding_legacy_content_does_not_distribute_private_assets(): void
    {
        Bus::fake();
        $business = Business::factory()->create();
        $device = Device::factory()->online()->create(['business_id' => $business->id]);
        $other = Device::factory()->online()->create();
        $media = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);
        $playlist = $business->playlists()->create(['name' => 'Privada', 'type' => 'business', 'status' => 'active']);
        $playlist->items()->create(['media_asset_id' => $media->id, 'duration' => 12, 'transition' => 'fade', 'sort_order' => 0]);
        $business->schedules()->create(['name' => 'Privada', 'playlist_id' => $playlist->id, 'status' => 'active']);
        $device->forceFill(['current_playlist_id' => $playlist->id])->save();

        $original = app(BuildDeviceManifest::class)->handle($other);
        app(\App\Domain\Scheduling\Jobs\RefreshBusinessManifests::class, ['businessId' => $business->id])
            ->handle(app(BuildDeviceManifest::class), app(\App\Domain\Devices\Actions\IssueDeviceCommand::class));
        $manifest = $this->poll($device)['payload'];
        $this->assertNull($manifest['business_playlist']);
        $this->assertSame([], $manifest['scheduled_playlists']);
        $this->assertSame([], $manifest['schedules']);
        $this->assertNotContains($media->id, array_column($manifest['assets'], 'id'));
        $this->assertSame($original->version, $other->fresh()->pending_manifest_version);
        $this->assertDatabaseHas('playlists', ['id' => $playlist->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
    }
}
