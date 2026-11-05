<?php

namespace Tests\Feature;

use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DeviceDiagnosticsTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_diagnostics_and_old_failures_are_visible_only_on_the_corresponding_device(): void
    {
        $device = Device::factory()->online()->create();
        $other = Device::factory()->online()->create();
        $diagnostics = [
            'sync_error' => 'MEDIA_42_DOWNLOAD_HTTP_404', 'last_sync_at' => 1234000,
            'business_images_expected' => 3, 'business_images_downloaded' => 2,
            'business_images_bytes' => 2048, 'media_directory' => '/data/user/0/tv.signage.player/files/signage/media',
        ];
        $this->withToken($device->issueToken())->postJson('/api/v1/device/heartbeat', ['diagnostics' => $diagnostics])->assertOk();
        $this->assertSame($diagnostics, $device->heartbeats()->sole()->diagnostics);
        $asset = MediaAsset::factory()->create();
        $failed = $device->playbackEvents()->create(['media_asset_id' => $asset->id, 'started_at' => now()->subDay(), 'error_code' => 'IMAGE_DECODE_ERROR']);
        for ($i = 0; $i < 20; $i++) {
            $device->playbackEvents()->create(['media_asset_id' => $asset->id, 'started_at' => now()->subMinutes($i), 'completed' => true]);
        }
        $other->playbackEvents()->create(['media_asset_id' => $asset->id, 'started_at' => now(), 'error_code' => 'VIDEO_STALLED']);
        $this->actingAs($this->superAdmin())->get("/admin/devices/{$device->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Devices/Show')
                ->has('failures', 1)->where('failures.0.id', $failed->id)
                ->where('heartbeats.0.diagnostics.sync_error', $diagnostics['sync_error'])
                ->where('heartbeats.0.diagnostics.business_images_downloaded', 2));
        $this->get("/admin/devices/{$other->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->has('heartbeats', 0)->has('failures', 1)->where('failures.0.error_code', 'VIDEO_STALLED'));
        $this->actingAs($this->businessUser($other->business))->get("/admin/devices/{$device->id}")->assertForbidden();
    }

    public function test_diagnostics_validate_shape_and_remain_optional_for_older_players(): void
    {
        $device = Device::factory()->online()->create();
        $this->withToken($device->issueToken());
        foreach ([['sync_error' => 'https://private.invalid/token'], ['business_images_downloaded' => -1], ['media_directory' => str_repeat('x', 256)], ['unknown' => 'value']] as $diagnostics) {
            $this->postJson('/api/v1/device/heartbeat', ['diagnostics' => $diagnostics])->assertUnprocessable();
        }
        $this->postJson('/api/v1/device/heartbeat', [])->assertOk();
        $this->assertNull($device->heartbeats()->sole()->diagnostics);
    }
}
