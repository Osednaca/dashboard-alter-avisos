<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Scheduling\Jobs\RefreshBusinessManifests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulePlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_schedules_and_playlist_are_preserved_but_never_included_in_manifests(): void
    {
        $business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $location = Location::factory()->create(['business_id' => $business->id]);
        $playlist = $business->playlists()->create(['name' => 'Anterior', 'type' => 'business', 'status' => 'active']);
        $schedule = $business->schedules()->create([
            'name' => 'Anterior', 'playlist_id' => $playlist->id, 'location_id' => $location->id,
            'daily_start_time' => '00:00', 'daily_end_time' => '23:59', 'days_of_week' => [], 'status' => 'active',
        ]);
        $device = Device::factory()->online()->create([
            'business_id' => $business->id, 'location_id' => $location->id, 'current_playlist_id' => $playlist->id,
        ]);

        $manifest = app(BuildDeviceManifest::class)->handle($device);
        $this->assertNull($manifest->payload['business_playlist']);
        $this->assertNull($manifest->payload['active_playlist_id']);
        $this->assertSame([], $manifest->payload['scheduled_playlists']);
        $this->assertSame([], $manifest->payload['schedules']);
        $this->assertSame($playlist->id, $device->fresh()->current_playlist_id);
        $this->assertDatabaseHas('content_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseHas('playlists', ['id' => $playlist->id]);
    }

    public function test_legacy_refresh_job_can_still_refresh_reachable_devices_without_exposing_schedules(): void
    {
        $device = Device::factory()->online()->create();
        (new RefreshBusinessManifests($device->business_id))->handle(
            app(BuildDeviceManifest::class), app(IssueDeviceCommand::class),
        );
        $this->assertDatabaseCount('device_manifests', 1);
        $this->assertDatabaseHas('device_commands', ['device_id' => $device->id, 'command' => 'SYNC_CONTENT']);
        $payload = $device->fresh()->manifests()->sole()->payload;
        $this->assertNull($payload['business_playlist']);
        $this->assertSame([], $payload['schedules']);
    }
}
