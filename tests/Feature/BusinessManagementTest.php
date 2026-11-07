<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Locations\Models\City;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function input(array $overrides = []): array
    {
        return ['name' => 'Local comercial', 'category' => 'other', 'status' => 'active', 'timezone' => 'America/Bogota', ...$overrides];
    }

    private function campaign(string $type, int|string $target, bool $exclude = false): Campaign
    {
        $campaign = Campaign::factory()->create(['status' => 'active', 'starts_at' => today(), 'ends_at' => today()->addDays(3)]);
        $campaign->targets()->create(['target_type' => $type,
            is_int($target) ? 'target_id' : 'target_value' => $target, 'is_exclusion' => $exclude]);
        $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->image()->create()->id,
            'duration' => 15, 'weight' => 1, 'position' => 0, 'status' => 'active']);

        return $campaign;
    }

    public function test_admin_can_create_edit_list_and_delete_an_unassociated_business(): void
    {
        $this->actingAs($this->superAdmin())->post('/admin/businesses', $this->input())
            ->assertSessionHasNoErrors()->assertRedirect();
        $business = Business::query()->where('slug', 'local-comercial')->sole();
        $this->get('/admin/businesses')->assertOk()->assertInertia(fn ($page) => $page->has('businesses.data', 1));
        $this->get("/admin/businesses/{$business->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('business.name', 'Local comercial')->missing('playlists')->missing('users'));
        $this->put("/admin/businesses/{$business->id}", $this->input(['name' => 'Nuevo nombre']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Nuevo nombre']);
        $this->delete("/admin/businesses/{$business->id}")->assertRedirect('/admin/businesses');
        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
        $this->get('/business/dashboard')->assertNotFound();
    }

    public function test_deletion_refuses_cascading_or_historical_data_and_internal_business_is_protected(): void
    {
        $business = Business::factory()->create();
        $device = Device::factory()->create(['business_id' => $business->id]);
        $this->actingAs($this->superAdmin())->delete("/admin/businesses/{$business->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
        $historical = Business::factory()->create();
        $this->campaign('business', $historical->id);
        $this->delete("/admin/businesses/{$historical->id}")->assertSessionHas('error');
        $internal = Business::factory()->create(['slug' => Business::INTERNAL_SLUG]);
        $this->get("/admin/businesses/{$internal->id}")->assertNotFound();
        $this->delete("/admin/businesses/{$internal->id}")->assertNotFound();
        $this->post('/admin/businesses', $this->input(['slug' => Business::INTERNAL_SLUG]))->assertSessionHasErrors('slug');
    }

    public function test_reassignment_preserves_city_and_historic_location_and_refreshes_targeted_campaigns(): void
    {
        Bus::fake();
        $old = Business::factory()->create(['category' => 'cafe']);
        $new = Business::factory()->create(['category' => 'gym']);
        $location = Location::factory()->create(['business_id' => $old->id, 'state' => 'Cundinamarca']);
        $device = Device::factory()->online()->create(['business_id' => $old->id, 'location_id' => $location->id]);
        $city = City::query()->create(['name' => 'Bogotá', 'state' => '', 'country' => 'Colombia', 'timezone' => 'America/Bogota', 'status' => 'active']);
        $city->devices()->attach($device->id);
        $oldCampaign = $this->campaign('business', $old->id);
        $newCampaign = $this->campaign('business', $new->id);
        $locationCampaign = $this->campaign('location', $location->id);
        $categoryCampaign = $this->campaign('business_category', 'gym');
        $excluded = $this->campaign('business_category', 'gym', true);
        $excluded->targets()->create(['target_type' => 'city', 'target_id' => $city->id, 'is_exclusion' => false]);
        $resolver = app(ResolveCampaignTargets::class);
        app(BuildDeviceManifest::class)->handle($device);

        $this->actingAs($this->superAdmin())->put("/admin/devices/{$device->id}/business", ['business_id' => $new->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $device->refresh();
        $this->assertSame($new->id, $device->business_id);
        $this->assertSame($location->id, $device->location_id);
        $this->assertDatabaseHas('city_device', ['city_id' => $city->id, 'device_id' => $device->id]);
        $this->assertTrue($device->manifest_dirty);
        $this->assertFalse($resolver->devicesFor($oldCampaign)->whereKey($device->id)->exists());
        $this->assertTrue($resolver->devicesFor($newCampaign)->whereKey($device->id)->exists());
        $this->assertTrue($resolver->devicesFor($categoryCampaign)->whereKey($device->id)->exists());
        $this->assertTrue($resolver->devicesFor($locationCampaign)->whereKey($device->id)->exists());
        $this->assertFalse($resolver->devicesFor($excluded)->whereKey($device->id)->exists());
        $this->assertSame(0, $oldCampaign->fresh()->target_screen_count);
        $this->assertSame(1, $newCampaign->fresh()->target_screen_count);
        $this->get("/admin/devices/{$device->id}")->assertInertia(fn ($page) => $page
            ->where('device.business.id', $new->id)->where('device.location', null));
        $manifest = app(BuildDeviceManifest::class)->handle($device)->payload;
        $this->assertSame(0, $manifest['layout']['business_percentage']);
        $this->assertSame(100, $manifest['layout']['advertising_percentage']);
        $this->assertNull($manifest['business_playlist']);
    }

    public function test_editing_business_category_reconciles_historical_campaigns(): void
    {
        Bus::fake();
        $business = Business::factory()->create(['category' => 'cafe']);
        $device = Device::factory()->online()->create(['business_id' => $business->id]);
        $campaign = $this->campaign('business_category', 'gym');
        $this->assertFalse(app(ResolveCampaignTargets::class)->devicesFor($campaign)->whereKey($device->id)->exists());
        $this->actingAs($this->superAdmin())->put("/admin/businesses/{$business->id}", $this->input(['category' => 'gym']))
            ->assertSessionHasNoErrors();
        $this->assertTrue($device->fresh()->manifest_dirty);
        $this->assertSame(1, $campaign->fresh()->target_screen_count);
        $this->assertTrue(app(ResolveCampaignTargets::class)->devicesFor($campaign)->whereKey($device->id)->exists());
    }

    public function test_selected_business_and_city_are_applied_on_activation(): void
    {
        $business = Business::factory()->create();
        $city = City::query()->create(['name' => 'Cali', 'state' => '', 'country' => 'Colombia', 'timezone' => 'America/Bogota', 'status' => 'active']);
        $activation = DeviceActivation::query()->create(['code' => 'BIZ123', 'device_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'status' => 'pending', 'expires_at' => now()->addHour()]);
        $this->actingAs($this->superAdmin())->post("/admin/activations/{$activation->id}/assign", [
            'business_id' => $business->id, 'city_id' => $city->id, 'device_name' => 'Aviso',
        ])->assertSessionHasNoErrors();
        $this->postJson('/api/v1/device/activation/confirm', ['code' => $activation->code, 'device_uuid' => $activation->device_uuid])->assertCreated();
        $device = $activation->fresh()->device;
        $this->assertSame($business->id, $device->business_id);
        $this->assertDatabaseHas('city_device', ['city_id' => $city->id, 'device_id' => $device->id]);
    }

    public function test_business_only_portal_users_cannot_manage_or_assign_businesses(): void
    {
        $business = Business::factory()->create();
        $device = Device::factory()->create();
        $this->actingAs($this->businessUser($business))->post('/admin/businesses', $this->input())->assertForbidden();
        $this->put("/admin/devices/{$device->id}/business", ['business_id' => $business->id])->assertForbidden();
        $this->assertNotSame($business->id, $device->fresh()->business_id);
    }

    public function test_assignment_rejects_invalid_and_internal_businesses_and_requires_device_manage(): void
    {
        $internal = Business::factory()->create(['slug' => Business::INTERNAL_SLUG]);
        $business = Business::factory()->create();
        $device = Device::factory()->create();
        $this->actingAs($this->superAdmin());
        foreach ([$internal->id, 999999] as $id) {
            $this->put("/admin/devices/{$device->id}/business", ['business_id' => $id])->assertSessionHasErrors('business_id');
        }
        $this->actingAs($this->userWithRole(RoleEnum::Support))
            ->put("/admin/devices/{$device->id}/business", ['business_id' => $business->id])->assertForbidden();
        $this->post('/admin/businesses', $this->input())->assertForbidden();
        $this->assertNotSame($business->id, $device->fresh()->business_id);
    }
}
