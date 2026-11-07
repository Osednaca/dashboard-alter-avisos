<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Locations\Models\City;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Users\Models\Permission;
use App\Domain\Users\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LedCityScopeTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function city(string $name = 'Bogotá', string $status = 'active'): City
    {
        return City::query()->create(['name' => $name, 'state' => '', 'country' => 'Colombia',
            'timezone' => 'America/Bogota', 'status' => $status]);
    }

    public function test_activation_assigns_a_city_without_requiring_a_business(): void
    {
        $city = $this->city();
        $activation = DeviceActivation::query()->create([
            'code' => 'LED123', 'device_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'status' => 'pending', 'expires_at' => now()->addHour(),
        ]);
        $this->actingAs($this->superAdmin())->post("/admin/activations/{$activation->id}/assign", [
            'city_id' => $city->id, 'device_name' => 'Aviso principal',
        ])->assertSessionHasNoErrors();
        $this->postJson('/api/v1/device/activation/confirm', [
            'code' => $activation->code, 'device_uuid' => $activation->device_uuid,
        ])->assertCreated();
        $this->assertDatabaseHas('city_device', ['city_id' => $city->id, 'device_id' => $activation->fresh()->device_id]);
    }

    public function test_quick_play_selects_only_devices_in_active_cities_even_if_business_is_shared(): void
    {
        $business = Business::factory()->create();
        $a = Device::factory()->online()->create(['business_id' => $business->id]);
        $b = Device::factory()->online()->create(['business_id' => $business->id]);
        $city = $this->city();
        $city->devices()->attach($a);
        $media = MediaAsset::factory()->image()->create();
        $this->actingAs($this->superAdmin())->get('/admin/quick-play/create')->assertOk()
            ->assertInertia(fn ($page) => $page->where('options.cities.0.device_ids.0', $a->id));
        $this->post('/admin/quick-play', ['media_asset_id' => $media->id, 'scope' => 'cities',
            'city_ids' => [$city->id], 'duration' => 10, 'display_mode' => 'fullscreen'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('quick_play_devices', ['device_id' => $a->id]);
        $this->assertDatabaseMissing('quick_play_devices', ['device_id' => $b->id]);
        $city->update(['status' => 'inactive']);
        $this->postJson('/admin/quick-play', ['media_asset_id' => $media->id, 'scope' => 'cities',
            'city_ids' => [$city->id], 'duration' => 10, 'display_mode' => 'fullscreen'])
            ->assertUnprocessable()->assertJsonValidationErrors('city_ids.0');
    }

    public function test_legacy_campaign_rules_are_hidden_and_unchanged_when_editing(): void
    {
        $included = Business::factory()->create(['name' => 'Local anterior', 'category' => 'other']);
        $excluded = Business::factory()->create(['category' => 'restaurant']);
        $a = Device::factory()->online()->create(['business_id' => $included->id]);
        $b = Device::factory()->online()->create(['business_id' => $excluded->id]);
        $campaign = Campaign::factory()->create(['status' => 'draft', 'starts_at' => today(), 'ends_at' => today()->addDays(3)]);
        $campaign->targets()->create(['target_type' => 'business', 'target_id' => $included->id, 'is_exclusion' => false]);
        $campaign->targets()->create(['target_type' => 'business_category', 'target_value' => 'restaurant', 'is_exclusion' => true]);
        $media = MediaAsset::factory()->image()->create();
        $campaign->creatives()->create(['media_asset_id' => $media->id, 'duration' => 15, 'weight' => 1, 'position' => 0, 'status' => 'active']);
        $resolver = app(ResolveCampaignTargets::class);
        $before = $resolver->devicesFor($campaign)->pluck('devices.id')->all();
        $this->assertSame([$a->id], $before);
        $this->assertNotContains($b->id, $before);
        $this->actingAs($this->superAdmin())->get("/admin/campaigns/{$campaign->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaign.has_legacy_targets', true)->has('campaign.targets', 0)
                ->missing('options.businesses')->missing('options.categories'));
        $this->put("/admin/campaigns/{$campaign->id}", [
            'advertiser_id' => $campaign->advertiser_id, 'name' => 'Editada', 'starts_at' => today()->toDateString(),
            'ends_at' => today()->addDays(3)->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $media->id, 'duration' => 15, 'weight' => 1]], 'targets' => [],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before, $resolver->devicesFor($campaign->fresh())->pluck('devices.id')->all());
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business_category', 'target_value' => 'restaurant', 'is_exclusion' => true]);
        $this->get('/admin/businesses')->assertOk();
    }

    public function test_role_editor_hides_legacy_permissions_without_deleting_them_on_save(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->where('name', 'operator')->firstOrFail();
        $legacy = Permission::query()->where('name', 'business.dashboard.view')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$legacy->id]);
        $page = $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $groups = $page->inertiaProps('permissionGroups');
        foreach ($groups as $permissions) {
            foreach ($permissions as $permission) {
                $this->assertFalse(str_starts_with($permission['value'], 'business.'));
            }
        }
        $this->put("/admin/settings/roles/{$role->id}/permissions", ['permissions' => ['devices.view']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($role->fresh()->permissions()->whereKey($legacy->id)->exists());
        $this->put("/admin/settings/roles/{$role->id}/permissions", ['permissions' => ['businesses.edit']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($role->fresh()->permissions()->where('name', 'businesses.edit')->exists());
    }
}
