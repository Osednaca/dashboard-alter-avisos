<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
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

    private function scenario(): array
    {
        Bus::fake(); // No worker or queued refresh runs in these scenarios.
        $business = Business::factory()->create();
        $user = $this->businessUser($business);
        $media = MediaAsset::factory()->image()->create([
            'owner_type' => $business->getMorphClass(), 'owner_id' => $business->id,
        ]);
        $device = Device::factory()->online()->create(['business_id' => $business->id]);

        return [$business, $user, $media, $device];
    }

    public function test_editing_the_same_managed_playlist_reaches_every_business_screen_without_a_worker(): void
    {
        [$business, $user, $media, $first] = $this->scenario();
        $second = Device::factory()->online()->create(['business_id' => $business->id]);
        $unrelated = Device::factory()->online()->create();
        $payload = ['name' => 'Programación', 'status' => 'active', 'items' => [
            ['media_asset_id' => $media->id, 'duration_seconds' => 12, 'transition' => 'fade'],
        ]];
        $this->actingAs($user)->post('/business/schedule', $payload)->assertSessionHasNoErrors();
        $schedule = $business->schedules()->sole();
        $original = [];
        foreach ([$first, $second, $unrelated] as $device) {
            app(BuildDeviceManifest::class)->handle($device);
            $original[$device->id] = $this->poll($device);
            $this->acknowledge($device, $original[$device->id]);
        }
        $payload['items'][0]['duration_seconds'] = 37;
        $this->actingAs($user)->put('/business/schedule/'.$schedule->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame($schedule->playlist_id, $schedule->fresh()->playlist_id);
        foreach ([$first, $second] as $device) {
            $latest = $this->poll($device);
            $this->assertSame(37, $latest['payload']['business_playlist']['items'][0]['duration']);
            $this->assertNotSame($original[$device->id]['checksum'], $latest['checksum']);
            $this->acknowledge($device, $latest);
        }
        $this->assertSame($original[$unrelated->id]['checksum'], $this->poll($unrelated)['checksum']);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_legacy_playlist_item_edits_reach_the_same_active_playlist_without_a_worker(): void
    {
        [$business, $user, $media, $device] = $this->scenario();
        $playlist = $business->playlists()->create(['name' => 'Lista', 'type' => 'business', 'status' => 'active']);
        $item = $playlist->items()->create(['media_asset_id' => $media->id, 'duration' => 12, 'transition' => 'fade', 'sort_order' => 0]);
        app(BuildDeviceManifest::class)->handle($device);
        $original = $this->poll($device);
        $this->acknowledge($device, $original);
        $this->actingAs($user)->put('/business/playlists/'.$playlist->id.'/items/'.$item->id, [
            'duration' => 41, 'transition' => 'none',
        ])->assertSessionHasNoErrors();
        $latest = $this->poll($device);
        $this->assertSame(41, $latest['payload']['business_playlist']['items'][0]['duration']);
        $this->assertNotSame($original['checksum'], $latest['checksum']);
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

    public function test_legacy_playlist_add_reorder_remove_rename_and_delete_are_delivered_without_a_worker(): void
    {
        [$business, $user, $media, $device] = $this->scenario();
        $playlist = $business->playlists()->create(['name' => 'Lista original', 'type' => 'business', 'status' => 'active']);
        $firstItem = $playlist->items()->create(['media_asset_id' => $media->id, 'duration' => 12, 'transition' => 'fade', 'sort_order' => 0]);
        app(BuildDeviceManifest::class)->handle($device);
        $original = $this->poll($device);
        $this->acknowledge($device, $original);
        $this->actingAs($user)->post('/business/playlists/'.$playlist->id.'/items', [
            'media_asset_id' => $media->id, 'duration' => 27, 'transition' => 'none',
        ])->assertSessionHasNoErrors();
        $added = $this->poll($device);
        $this->assertSame([12, 27], array_column($added['payload']['business_playlist']['items'], 'duration'));
        $this->acknowledge($device, $added);
        $secondItem = $playlist->items()->whereKeyNot($firstItem->id)->sole();
        $this->actingAs($user)->put('/business/playlists/'.$playlist->id.'/items/reorder', [
            'order' => [$secondItem->id, $firstItem->id],
        ])->assertSessionHasNoErrors();
        $reordered = $this->poll($device);
        $this->assertSame([27, 12], array_column($reordered['payload']['business_playlist']['items'], 'duration'));
        $this->acknowledge($device, $reordered);
        $this->actingAs($user)->delete('/business/playlists/'.$playlist->id.'/items/'.$firstItem->id)->assertRedirect();
        $removed = $this->poll($device);
        $this->assertSame([27], array_column($removed['payload']['business_playlist']['items'], 'duration'));
        $this->acknowledge($device, $removed);
        $this->actingAs($user)->put('/business/playlists/'.$playlist->id, ['name' => 'Lista renombrada'])->assertSessionHasNoErrors();
        $renamed = $this->poll($device);
        $this->assertSame('Lista renombrada', $renamed['payload']['business_playlist']['name']);
        $this->acknowledge($device, $renamed);
        $this->actingAs($user)->delete('/business/playlists/'.$playlist->id)->assertRedirect();
        $deleted = $this->poll($device);
        $this->assertNull($deleted['payload']['business_playlist']);
        $this->assertSame([], $deleted['payload']['scheduled_playlists']);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_schedule_location_move_and_delete_refresh_old_and_new_screens_without_a_worker(): void
    {
        [$business, $user, $media, $old] = $this->scenario();
        $oldLocation = Location::factory()->create(['business_id' => $business->id]);
        $newLocation = Location::factory()->create(['business_id' => $business->id]);
        $old->update(['location_id' => $oldLocation->id]);
        $new = Device::factory()->online()->create(['business_id' => $business->id, 'location_id' => $newLocation->id]);
        foreach ([$old, $new] as $device) {
            app(BuildDeviceManifest::class)->handle($device);
            $this->acknowledge($device, $this->poll($device));
        }
        $payload = ['name' => 'Por sucursal', 'status' => 'active', 'location_id' => $oldLocation->id, 'items' => [
            ['media_asset_id' => $media->id, 'duration_seconds' => 16, 'transition' => 'fade'],
        ]];
        $this->actingAs($user)->post('/business/schedule', $payload)->assertSessionHasNoErrors();
        $schedule = $business->schedules()->sole();
        $initial = $this->poll($old);
        $this->assertSame($schedule->playlist_id, $initial['payload']['business_playlist']['id']);
        $this->acknowledge($old, $initial);
        $this->assertNull($this->poll($new)['payload']['business_playlist']);
        $this->acknowledge($new, $this->poll($new));
        $payload['location_id'] = $newLocation->id;
        $this->actingAs($user)->put('/business/schedule/'.$schedule->id, $payload)->assertSessionHasNoErrors();
        $movedOld = $this->poll($old);
        $movedNew = $this->poll($new);
        $this->assertNull($movedOld['payload']['business_playlist']);
        $this->assertSame([], $movedOld['payload']['scheduled_playlists']);
        $this->assertSame($schedule->playlist_id, $movedNew['payload']['business_playlist']['id']);
        $this->acknowledge($old, $movedOld);
        $this->acknowledge($new, $movedNew);
        $this->actingAs($user)->delete('/business/schedule/'.$schedule->id)->assertRedirect();
        $deleted = $this->poll($new);
        $this->assertNull($deleted['payload']['business_playlist']);
        $this->assertSame([], $deleted['payload']['scheduled_playlists']);
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
    }
}
