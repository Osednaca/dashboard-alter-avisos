<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Playlists\Enums\PlaylistStatus;
use App\Domain\Playlists\Enums\PlaylistType;
use App\Domain\Playlists\Models\Playlist;
use App\Domain\Scheduling\Jobs\RefreshBusinessManifests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SchedulePlaybackTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function playlist(Business $business, string $name): Playlist
    {
        return $business->playlists()->create([
            'name' => $name,
            'type' => PlaylistType::Business,
            'status' => PlaylistStatus::Active,
        ]);
    }

    protected function schedule(Business $business, Playlist $playlist, array $attributes = []): void
    {
        $business->schedules()->create([
            'name' => $attributes['name'] ?? 'Franja',
            'playlist_id' => $playlist->id,
            'location_id' => $attributes['location_id'] ?? null,
            'daily_start_time' => $attributes['start'] ?? '00:00',
            'daily_end_time' => $attributes['end'] ?? '23:59',
            'days_of_week' => $attributes['days'] ?? [],
            'status' => 'active',
        ]);
    }

    protected function scenario(): array
    {
        $business = Business::factory()->create(['timezone' => 'America/Bogota']);
        $location = Location::factory()->create(['business_id' => $business->id]);
        $device = Device::factory()->online()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);

        $fallback = $this->playlist($business, 'General');
        $device->forceFill(['current_playlist_id' => $fallback->id])->save();

        return [$business, $location, $device->fresh(), $fallback];
    }

    public function test_the_active_schedule_drives_the_manifest_playlist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Bogota'));

        [$business, $location, $device, $fallback] = $this->scenario();
        $breakfast = $this->playlist($business, 'Desayuno');
        $this->schedule($business, $breakfast, ['name' => 'Desayuno', 'start' => '08:00', 'end' => '12:00']);

        $manifest = app(BuildDeviceManifest::class)->handle($device);

        $this->assertSame($breakfast->id, $manifest->payload['business_playlist']['id']);
        $this->assertSame($breakfast->id, $manifest->payload['active_playlist_id']);
        $this->assertSame($breakfast->id, $device->fresh()->current_playlist_id);
        $this->assertNotEmpty($manifest->payload['schedules']);
        $this->assertSame($breakfast->id, $manifest->payload['schedules'][0]['playlist_id']);
    }

    public function test_outside_the_window_it_falls_back_to_the_default_playlist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 20:00:00', 'America/Bogota'));

        [$business, $location, $device, $fallback] = $this->scenario();
        $breakfast = $this->playlist($business, 'Desayuno');
        $this->schedule($business, $breakfast, ['start' => '08:00', 'end' => '12:00']);

        $manifest = app(BuildDeviceManifest::class)->handle($device);

        $this->assertSame($fallback->id, $manifest->payload['business_playlist']['id']);
    }

    public function test_a_location_schedule_does_not_affect_other_locations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Bogota'));

        [$business, $locationA, $deviceA, $fallback] = $this->scenario();
        $locationB = Location::factory()->create(['business_id' => $business->id]);
        $deviceB = Device::factory()->online()->create([
            'business_id' => $business->id,
            'location_id' => $locationB->id,
            'current_playlist_id' => $fallback->id,
        ]);

        $special = $this->playlist($business, 'Solo A');
        $this->schedule($business, $special, ['location_id' => $locationA->id, 'start' => '08:00', 'end' => '12:00']);

        $manifestA = app(BuildDeviceManifest::class)->handle($deviceA);
        $manifestB = app(BuildDeviceManifest::class)->handle($deviceB->fresh());

        $this->assertSame($special->id, $manifestA->payload['business_playlist']['id']);
        $this->assertSame($fallback->id, $manifestB->payload['business_playlist']['id']);
    }

    public function test_overnight_windows_wrap_around_midnight(): void
    {
        [$business, $location, $device] = $this->scenario();
        $late = $this->playlist($business, 'Nocturno');
        $this->schedule($business, $late, ['start' => '20:00', 'end' => '02:00']);

        Carbon::setTestNow(Carbon::parse('2026-03-10 23:00:00', 'America/Bogota'));
        $this->assertSame($late->id, app(BuildDeviceManifest::class)->handle($device->fresh())->payload['business_playlist']['id']);

        Carbon::setTestNow(Carbon::parse('2026-03-11 01:00:00', 'America/Bogota'));
        $this->assertSame($late->id, app(BuildDeviceManifest::class)->handle($device->fresh())->payload['business_playlist']['id']);
    }

    public function test_saving_a_schedule_refreshes_the_business_manifests(): void
    {
        Queue::fake();

        [$business, $location, $device, $fallback] = $this->scenario();
        $playlist = $this->playlist($business, 'Almuerzo');
        $user = $this->businessUser($business);

        $this->actingAs($user)
            ->post('/business/schedule', [
                'name' => 'Almuerzo',
                'playlist_id' => $playlist->id,
                'daily_start_time' => '11:00',
                'daily_end_time' => '16:00',
                'days_of_week' => [],
                'status' => 'active',
            ])
            ->assertRedirect();

        Queue::assertPushed(RefreshBusinessManifests::class, fn ($job) => $job->businessId === $business->id);
    }

    public function test_the_refresh_job_rebuilds_manifests_and_commands_reachable_screens(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Bogota'));

        [$business, $location, $device, $fallback] = $this->scenario();
        $breakfast = $this->playlist($business, 'Desayuno');
        $this->schedule($business, $breakfast, ['start' => '08:00', 'end' => '12:00']);

        (new RefreshBusinessManifests($business->id))->handle(
            app(BuildDeviceManifest::class),
            app(IssueDeviceCommand::class),
        );

        $this->assertDatabaseCount('device_manifests', 1);
        $this->assertDatabaseHas('device_commands', [
            'device_id' => $device->id,
            'command' => 'SYNC_CONTENT',
        ]);
        $this->assertSame($breakfast->id, $device->fresh()->current_playlist_id);
    }

    public function test_sync_reconciles_the_active_scheduled_playlist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00', 'America/Bogota'));

        [$business, $location, $device, $fallback] = $this->scenario();
        $breakfast = $this->playlist($business, 'Desayuno');
        $this->schedule($business, $breakfast, ['start' => '08:00', 'end' => '12:00']);

        $response = $this->withToken($device->issueToken())
            ->getJson('/api/v1/device/sync')
            ->assertOk();

        $this->assertTrue($response->json('update_available'));
        $this->assertNotNull($response->json('pending_manifest_version'));
        $this->assertSame($breakfast->id, $device->fresh()->current_playlist_id);
    }
}
