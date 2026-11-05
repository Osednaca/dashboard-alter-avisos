<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\PublishCampaign;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CityManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function city(array $businessIds = [], array $attributes = []): City
    {
        $city = City::query()->create([
            'name' => 'Bogotá', 'state' => 'Cundinamarca', 'country' => 'Colombia',
            'timezone' => 'America/Bogota', 'status' => 'active', ...$attributes,
        ]);
        $city->businesses()->sync($businessIds);

        return $city;
    }

    private function input(array $ids, array $overrides = []): array
    {
        return ['name' => 'Bogotá', 'state' => 'Cundinamarca', 'country' => 'Colombia',
            'timezone' => 'America/Bogota', 'status' => 'active', 'business_ids' => $ids, ...$overrides];
    }

    private function campaign(array $target): Campaign
    {
        $campaign = Campaign::factory()->create(['status' => 'draft', 'starts_at' => today(), 'ends_at' => today()->addWeek()]);
        $campaign->targets()->create(['target_type' => 'city', ...$target]);
        $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->image()->create()->id,
            'duration' => 15, 'weight' => 1, 'position' => 0, 'status' => 'active']);

        return $campaign;
    }

    private function resolved(Campaign $campaign): array
    {
        return app(ResolveCampaignTargets::class)->devicesFor($campaign)->orderBy('devices.id')->pluck('devices.id')->all();
    }

    private function poll(Device $device): array
    {
        $this->withToken($device->issueToken())->getJson('/api/v1/device/sync')->assertOk();

        return $this->getJson('/api/v1/device/manifest')->assertOk()->json('manifest.payload.advertising_playlist.campaigns');
    }

    public function test_admin_crud_assigns_multiple_businesses_and_preserves_branches_on_delete(): void
    {
        Bus::fake();
        $branch = Location::factory()->create();
        $other = Business::factory()->create();
        $device = Device::factory()->online()->create(['business_id' => $other->id, 'location_id' => null]);
        $this->actingAs($this->superAdmin())->post('/admin/locations', $this->input([$branch->business_id, $other->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $city = City::query()->sole();
        $this->assertCount(2, $city->businesses);
        $this->get('/admin/locations')->assertOk()->assertInertia(fn ($page) => $page
            ->has('locations.data.0.businesses', 2)->where('locations.data.0.devices_count', 1));
        $this->get("/admin/locations/{$city->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('devices.0.id', $device->id)->has('location.businesses', 2));
        $this->delete("/admin/locations/{$city->id}")->assertRedirect('/admin/locations');
        $this->assertSoftDeleted('cities', ['id' => $city->id]);
        $this->assertDatabaseHas('locations', ['id' => $branch->id, 'business_id' => $branch->business_id]);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseCount('city_business', 0);
        $this->post('/admin/locations', $this->input([$other->id]))->assertSessionHasErrors('name');
    }

    public function test_business_users_cannot_manage_or_read_admin_cities_and_invalid_membership_is_atomic(): void
    {
        $business = Business::factory()->create();
        $city = $this->city([$business->id]);
        $this->actingAs($this->businessUser($business))->get('/admin/locations')->assertForbidden();
        $this->put("/admin/locations/{$city->id}", $this->input([$business->id]))->assertForbidden();
        $this->delete("/admin/locations/{$city->id}")->assertForbidden();
        $this->actingAs($this->superAdmin())->put("/admin/locations/{$city->id}", $this->input([999999]))
            ->assertSessionHasErrors('business_ids.0');
        $this->assertSame([$business->id], $city->businesses()->pluck('businesses.id')->all());
        $this->post('/admin/locations', $this->input([], ['name' => 'Cali']))->assertSessionHasErrors('business_ids');
    }

    public function test_city_id_and_legacy_name_reach_every_eligible_screen_of_assigned_businesses(): void
    {
        $branch = Location::factory()->create(['city' => 'Otra ciudad']);
        $business = Business::factory()->create();
        $a = Device::factory()->online()->create(['business_id' => $branch->business_id, 'location_id' => $branch->id]);
        $b = Device::factory()->online()->create(['business_id' => $business->id, 'location_id' => null]);
        Device::factory()->create(['business_id' => $business->id, 'status' => 'disabled']);
        Device::factory()->create(['business_id' => $business->id, 'status' => 'pending_activation']);
        Device::factory()->online()->create();
        $city = $this->city([$branch->business_id, $business->id]);
        $expected = [$a->id, $b->id];
        $this->assertSame($expected, $this->resolved($this->campaign(['target_id' => $city->id])));
        $this->assertSame($expected, $this->resolved($this->campaign(['target_value' => 'Bogotá'])));
        $summary = app(ResolveCampaignTargets::class)->summary($this->campaign(['target_id' => $city->id]));
        $this->assertSame(2, $summary['businesses']);
        $this->assertSame(2, $summary['screens']);
        $this->assertSame(1, $summary['cities']); // catalogue coverage replaces assigned businesses' branch names
    }

    public function test_legacy_fallback_is_blocked_for_inactive_deleted_or_unassigned_catalogue_city(): void
    {
        $branch = Location::factory()->create(['city' => 'Bogotá']);
        $device = Device::factory()->online()->create(['business_id' => $branch->business_id, 'location_id' => $branch->id]);
        $campaign = $this->campaign(['target_value' => 'Bogotá']);
        $this->assertSame([$device->id], $this->resolved($campaign));
        $city = $this->city();
        $this->assertSame([], $this->resolved($campaign));
        $city->businesses()->sync([$branch->business_id]);
        $city->update(['status' => 'inactive']);
        $this->assertSame([], $this->resolved($campaign));
        $city->update(['status' => 'active']);
        $city->delete();
        $this->assertSame([], $this->resolved($campaign));
    }

    public function test_city_summary_counts_catalogue_members_once_despite_different_legacy_branch_names(): void
    {
        $bogota = Location::factory()->create(['city' => 'Bogotá anterior']);
        $cali = Location::factory()->create(['city' => 'Cali anterior']);
        foreach ([$bogota, $cali] as $branch) {
            Device::factory()->online()->create(['business_id' => $branch->business_id, 'location_id' => $branch->id]);
        }
        $city = $this->city([$bogota->business_id, $cali->business_id]);
        $campaign = $this->campaign(['target_id' => $city->id]);
        $resolver = app(ResolveCampaignTargets::class);
        $this->assertSame(['screens' => 2, 'businesses' => 2, 'locations' => 2, 'cities' => 1], $resolver->summary($campaign));

        // An unrelated legacy business without a catalogue city retains its city coverage.
        $legacy = Location::factory()->create(['city' => 'Medellín anterior']);
        Device::factory()->online()->create(['business_id' => $legacy->business_id, 'location_id' => $legacy->id]);
        $campaign->targets()->create(['target_type' => 'city', 'target_value' => $legacy->city]);
        $this->assertSame(['screens' => 3, 'businesses' => 3, 'locations' => 3, 'cities' => 2], $resolver->summary($campaign));
    }

    public function test_city_exclusion_subtracts_assigned_businesses_without_losing_branchless_screens(): void
    {
        $a = Device::factory()->online()->create(['location_id' => null]);
        $b = Device::factory()->online()->create(['location_id' => null]);
        $city = $this->city([$a->business_id]);
        $campaign = $this->campaign(['target_id' => $city->id, 'is_exclusion' => true]);
        foreach ([$a, $b] as $device) {
            $campaign->targets()->create(['target_type' => 'business', 'target_id' => $device->business_id]);
        }
        $this->assertSame([$b->id], $this->resolved($campaign));
    }

    public function test_duplicate_city_names_use_all_catalogue_members_and_id_disambiguates(): void
    {
        $a = Device::factory()->online()->create(['location_id' => null]);
        $b = Device::factory()->online()->create(['location_id' => null]);
        $city = $this->city([$a->business_id]);
        $this->city([$b->business_id], ['state' => 'Otro departamento']);
        $this->assertSame([$a->id, $b->id], $this->resolved($this->campaign(['target_value' => 'Bogotá'])));
        $this->assertSame([$a->id], $this->resolved($this->campaign(['target_id' => $city->id])));
    }

    public function test_membership_and_status_changes_invalidate_old_and_new_tv_manifests(): void
    {
        Bus::fake();
        $old = Device::factory()->online()->create(['location_id' => null]);
        $new = Device::factory()->online()->create(['location_id' => null]);
        $city = $this->city([$old->business_id]);
        $campaign = $this->campaign(['target_id' => $city->id]);
        app(PublishCampaign::class)->handle($campaign);
        $this->assertSame([$campaign->id], array_column($this->poll($old), 'id'));
        $this->assertSame([], $this->poll($new));
        $this->actingAs($this->superAdmin())->put("/admin/locations/{$city->id}", $this->input([$new->business_id], ['name' => 'Bogotá renombrada']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $campaign->fresh()->target_screen_count);
        $this->assertSame([], $this->poll($old));
        $this->assertSame([$campaign->id], array_column($this->poll($new), 'id'));
        $this->actingAs($this->superAdmin())->put("/admin/locations/{$city->id}", $this->input([$new->business_id], ['name' => 'Bogotá renombrada', 'status' => 'inactive']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $campaign->fresh()->target_screen_count);
        $this->assertSame([], $this->poll($new));
        $this->actingAs($this->superAdmin())->put("/admin/locations/{$city->id}", $this->input([$new->business_id], ['name' => 'Bogotá renombrada']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$campaign->id], array_column($this->poll($new), 'id'));
        $this->actingAs($this->superAdmin())->delete("/admin/locations/{$city->id}")->assertRedirect();
        $this->assertSame(0, $campaign->fresh()->target_screen_count);
        $this->assertSame([], $this->poll($new));
    }

    public function test_new_city_target_ids_are_validated_and_legacy_text_remains_compatible(): void
    {
        $city = $this->city([Business::factory()->create()->id]);
        $campaign = $this->campaign(['target_value' => 'Bogotá']);
        $body = ['advertiser_id' => $campaign->advertiser_id, 'name' => 'Ciudad', 'starts_at' => today()->toDateString(),
            'ends_at' => today()->addWeek()->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $campaign->creatives()->sole()->media_asset_id, 'duration' => 15, 'weight' => 1]]];
        $this->actingAs($this->superAdmin());
        foreach ([999999, 0, -1] as $id) {
            $this->postJson('/admin/campaigns', [...$body, 'targets' => [['target_type' => 'city', 'target_id' => $id]]])
                ->assertUnprocessable()->assertJsonValidationErrors('targets.0.target_id');
        }
        $this->postJson('/admin/campaigns', [...$body, 'targets' => [['target_type' => 'city', 'target_id' => $city->id]]])->assertRedirect();
        $city->update(['status' => 'inactive']);
        $this->postJson('/admin/campaigns', [...$body, 'targets' => [['target_type' => 'city', 'target_id' => $city->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('targets.0.target_id');
        $this->postJson('/admin/campaigns', [...$body, 'targets' => [['target_type' => 'city', 'target_value' => 'Bogotá']]])->assertRedirect();
    }

    public function test_preview_uses_submitted_city_ids_and_rejects_invalid_rules_and_inactive_ids(): void
    {
        $device = Device::factory()->online()->create(['location_id' => null]);
        $city = $this->city([$device->business_id]);
        $this->actingAs($this->superAdmin())->postJson('/admin/campaigns/preview-targets', ['targets' => [
            ['target_type' => 'city', 'target_id' => $city->id],
        ]])->assertOk()->assertJsonPath('summary.screens', 1)->assertJsonPath('summary.cities', 1);
        $this->postJson('/admin/campaigns/preview-targets', ['targets' => []])->assertOk()->assertJsonPath('summary.screens', 0);
        foreach ([['target_type' => 'city', 'target_id' => 999999], ['target_type' => 'unknown'], 'city'] as $rule) {
            $this->postJson('/admin/campaigns/preview-targets', ['targets' => [$rule]])->assertUnprocessable();
        }
        $this->get('/admin/campaigns/create')->assertOk()->assertInertia(fn ($page) => $page
            ->where('options.cities.0.id', $city->id)->where('options.cities.0.name', 'Bogotá'));
        $city->update(['status' => 'inactive']);
        $this->postJson('/admin/campaigns/preview-targets', ['targets' => [['target_type' => 'city', 'target_id' => $city->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('targets.0.target_id');
        $this->get('/admin/campaigns/create')->assertOk()->assertInertia(fn ($page) => $page->has('options.cities', 0));
    }

    public function test_renaming_a_city_preserves_unambiguous_legacy_campaign_identity(): void
    {
        Bus::fake();
        $device = Device::factory()->online()->create(['location_id' => null]);
        $city = $this->city([$device->business_id]);
        $campaign = $this->campaign(['target_value' => 'Bogotá']);
        $this->actingAs($this->superAdmin())->put("/admin/locations/{$city->id}", $this->input([$device->business_id], ['name' => 'Bogotá nueva']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($city->id, $campaign->targets()->sole()->target_id);
        $this->assertNull($campaign->targets()->sole()->target_value);
        $this->assertSame([$device->id], $this->resolved($campaign));
    }

    public function test_additive_migration_backfills_unique_regions_and_memberships_without_touching_branch_ids(): void
    {
        $branch = Location::factory()->create(['city' => 'Bogotá', 'state' => null]);
        Location::factory()->create(['business_id' => $branch->business_id, 'city' => 'Bogotá', 'state' => null]);
        $other = Location::factory()->create(['city' => 'Bogotá', 'state' => null]);
        Location::factory()->create(['city' => 'Bogotá', 'state' => 'Otro']);
        $device = Device::factory()->online()->create(['business_id' => $branch->business_id, 'location_id' => $branch->id]);
        Device::factory()->online()->create(['business_id' => $branch->business_id, 'location_id' => null]);
        $campaign = $this->campaign(['target_value' => 'Bogotá']);
        $campaign->forceFill(['target_screen_count' => 1])->save();
        $migration = require database_path('migrations/2026_10_07_120000_create_cities_tables.php');
        // Only this test's isolated in-memory DB; exercise up/down with pre-existing branches.
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('cities', 2);
        $this->assertDatabaseCount('city_business', 3);
        $city = City::query()->where('state', '')->sole();
        $this->assertEqualsCanonicalizing([$branch->business_id, $other->business_id], $city->businesses()->pluck('businesses.id')->all());
        $this->assertSame($branch->id, $device->fresh()->location_id);
        $this->assertTrue($device->fresh()->manifest_dirty);
        $this->assertSame(2, $campaign->fresh()->target_screen_count);
        $migration->down();
        $this->assertDatabaseCount('locations', 4);
        $this->assertSame($branch->id, $device->fresh()->location_id);
    }
}
