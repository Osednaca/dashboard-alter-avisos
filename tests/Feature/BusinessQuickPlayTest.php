<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessQuickPlayTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function fixture(): array
    {
        $business = Business::factory()->create();
        $location = Location::factory()->create(['business_id' => $business->id]);
        $device = Device::factory()->online()->create(['business_id' => $business->id, 'location_id' => $location->id]);
        $media = MediaAsset::factory()->image()->create(['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id]);

        return [$business, $device, $media, $location];
    }

    private function payload(MediaAsset $media, array $patch = []): array
    {
        return array_replace(['media_asset_id' => $media->id, 'display_mode' => 'business', 'scope' => 'devices', 'device_ids' => Device::where('business_id', $media->owner_id)->pluck('id')->all(), 'duration' => 10], $patch);
    }

    public function test_options_and_delivery_only_include_the_current_business_screens_and_zone(): void
    {
        [$business, $device, $media] = $this->fixture();
        $this->fixture();
        $this->actingAs($this->businessUser($business))->get('/business/quick-play/create')
            ->assertOk()->assertInertia(fn ($page) => $page->component('Admin/QuickPlay/Create')
            ->where('portal', 'business')->has('options.media', 1)->has('options.devices', 1)
            ->has('options.businesses', 0)->has('options.locations', 0)
            ->has('options.scopes', 1)->where('options.scopes.0.value', 'devices')
            ->has('options.displayModes', 1)->where('options.displayModes.0.value', 'business')
            ->where('options.devices.0.id', $device->id)->where('options.media.0.id', $media->id));
        $this->post('/business/quick-play', $this->payload($media))->assertSessionHasNoErrors()->assertRedirect();
        $quick = QuickPlay::firstOrFail();
        $this->assertSame($business->id, $quick->business_id);
        $this->assertSame('business', $quick->devices()->sole()->command->payload['display_mode']);
        $this->assertDatabaseCount('device_commands', 1);
        $this->assertDatabaseHas('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $device->id]);
        $this->get('/business/quick-play/'.$quick->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('portal', 'business')->has('devices', 1));
    }

    public function test_foreign_media_and_every_foreign_target_scope_are_rejected(): void
    {
        [$business, , $media] = $this->fixture();
        [$other, $otherDevice, $otherMedia, $otherLocation] = $this->fixture();
        $this->actingAs($this->businessUser($business));
        foreach ([
            [$this->payload($otherMedia), 'media_asset_id'],
            [$this->payload($media, ['scope' => 'devices', 'device_ids' => [$otherDevice->id]]), 'device_ids.0'],
            [$this->payload($media, ['scope' => 'businesses', 'business_ids' => [$other->id]]), 'business_ids.0'],
            [$this->payload($media, ['scope' => 'locations', 'location_ids' => [$otherLocation->id]]), 'location_ids.0'],
        ] as [$payload, $error]) {
            $this->post('/business/quick-play', $payload)->assertSessionHasErrors($error);
        }
        $this->assertDatabaseCount('quick_plays', 0);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_multiple_own_screens_are_allowed_but_mixed_ownership_is_rejected(): void
    {
        [$business, $device, $media, $location] = $this->fixture();
        $second = Device::factory()->online()->create(['business_id' => $business->id, 'location_id' => $location->id]);
        [, $foreign] = $this->fixture();
        $this->actingAs($this->businessUser($business));
        $this->post('/business/quick-play', $this->payload($media, ['device_ids' => [$device->id, $foreign->id]]))
            ->assertSessionHasErrors('device_ids.1');
        $this->assertDatabaseCount('device_commands', 0);
        $this->post('/business/quick-play', $this->payload($media, ['device_ids' => [$device->id, $second->id]]))
            ->assertSessionHasNoErrors();
        $quick = QuickPlay::sole();
        $this->assertEqualsCanonicalizing([$device->id, $second->id], $quick->devices()->pluck('device_id')->all());
        foreach ($quick->devices as $row) {
            $this->assertSame('business', $row->command->payload['display_mode']);
        }
        $this->assertDatabaseCount('device_commands', 2);
    }

    public function test_malformed_scope_and_mode_return_validation_errors(): void
    {
        [$business, , $media] = $this->fixture();
        $this->actingAs($this->businessUser($business))->postJson('/business/quick-play', $this->payload($media, [
            'scope' => ['devices'], 'display_mode' => ['business'],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['scope', 'display_mode']);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_admin_keeps_all_destination_and_display_options(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin/quick-play/create')->assertOk()
            ->assertInertia(fn ($page) => $page->has('options.scopes', 4)->has('options.displayModes', 3));
    }

    public function test_history_cannot_be_read_from_another_business_even_for_a_shared_user(): void
    {
        [$business, , $media] = $this->fixture();
        [$other] = $this->fixture();
        $user = $this->businessUser($business);
        $other->users()->attach($user, ['role' => 'owner', 'is_primary' => false]);
        $this->actingAs($user)->post('/business/quick-play', $this->payload($media))->assertSessionHasNoErrors();
        $quick = QuickPlay::firstOrFail();
        $this->withSession(['business_id' => $other->id])->get('/business/quick-play/'.$quick->id)->assertNotFound();
        $this->get('/business/quick-play')->assertOk()
            ->assertInertia(fn ($page) => $page->where('portal', 'business')->has('quickPlays.data', 0));
    }

    public function test_business_cannot_send_to_advertising_or_fullscreen_or_use_group_scopes(): void
    {
        [$business, $device, $media, $location] = $this->fixture();
        $this->actingAs($this->businessUser($business));
        foreach (['advertising', 'fullscreen'] as $mode) {
            $this->post('/business/quick-play', $this->payload($media, ['display_mode' => $mode]))
                ->assertSessionHasErrors('display_mode');
        }
        foreach (['all', 'locations', 'businesses'] as $scope) {
            $this->post('/business/quick-play', $this->payload($media, ['scope' => $scope, 'location_ids' => [$location->id], 'business_ids' => [$business->id]]))
                ->assertSessionHasErrors('scope');
        }
        foreach (['business_ids' => [$business->id], 'location_ids' => [$location->id]] as $field => $ids) {
            $this->post('/business/quick-play', $this->payload($media, [$field => $ids]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('quick_plays', 0);
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_empty_targets_missing_duration_and_unready_media_do_not_send(): void
    {
        [$business, , $media] = $this->fixture();
        $this->actingAs($this->businessUser($business));
        $this->post('/business/quick-play', $this->payload($media, ['device_ids' => []]))->assertSessionHasErrors('targets');
        $this->post('/business/quick-play', $this->payload($media, ['duration' => null]))->assertSessionHasErrors('duration');
        $media->update(['processing_status' => 'pending']);
        $this->post('/business/quick-play', $this->payload($media))->assertSessionHasErrors('media_asset_id');
        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_sending_requires_business_permission_and_does_not_open_admin_routes(): void
    {
        [$business, , $media] = $this->fixture();
        $user = $this->businessUser($business);
        $this->actingAs($user)->get('/admin/quick-play')->assertForbidden();
        $user->syncRoles([]);
        $this->actingAs($user->fresh())->post('/business/quick-play', $this->payload($media))->assertForbidden();
        $this->assertDatabaseCount('device_commands', 0);
    }
}
