<?php

namespace Tests\Feature;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\City;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Models\QuickPlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessTargetingTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function body(MediaAsset $media, array $targets): array
    {
        return ['advertiser_id' => Advertiser::factory()->create()->id,
            'name' => 'Campaña', 'starts_at' => today()->toDateString(), 'ends_at' => today()->addWeek()->toDateString(),
            'priority' => 5, 'creatives' => [['media_asset_id' => $media->id, 'duration' => 15, 'weight' => 1]], 'targets' => $targets];
    }

    public function test_campaign_preview_and_creation_combine_inclusions_and_subtract_exclusions(): void
    {
        $a = Business::factory()->create();
        $b = Business::factory()->create();
        $first = Device::factory()->online()->create(['business_id' => $a->id]);
        $second = Device::factory()->online()->create(['business_id' => $b->id]);
        $third = Device::factory()->online()->create();
        Device::factory()->create(['business_id' => $a->id, 'status' => 'disabled']);
        $city = City::query()->create(['name' => 'Cali', 'state' => '', 'country' => 'Colombia', 'timezone' => 'America/Bogota', 'status' => 'active']);
        $city->devices()->attach([$first->id, $second->id]);
        $targets = [
            ['target_type' => 'business', 'target_id' => $a->id],
            ['target_type' => 'city', 'target_id' => $city->id],
            ['target_type' => 'device', 'target_id' => $third->id],
            ['target_type' => 'business', 'target_id' => $a->id, 'is_exclusion' => true],
        ];
        $this->actingAs($this->superAdmin())->postJson('/admin/campaigns/preview-targets', ['targets' => $targets])
            ->assertOk()->assertJsonPath('summary.screens', 2);
        $this->post('/admin/campaigns', $this->body(MediaAsset::factory()->image()->create(), $targets))->assertSessionHasNoErrors();
        $campaign = Campaign::query()->sole();
        $this->assertEqualsCanonicalizing([$second->id, $third->id], app(ResolveCampaignTargets::class)->devicesFor($campaign)->pluck('devices.id')->all());
        $this->assertDatabaseCount('campaign_targets', 4);
    }

    public function test_exclusion_only_includes_all_eligible_devices_except_the_business(): void
    {
        $business = Business::factory()->create();
        $excluded = Device::factory()->online()->create(['business_id' => $business->id]);
        $included = Device::factory()->online()->create();
        Device::factory()->create(['status' => 'disabled']);
        $targets = [['target_type' => 'business', 'target_id' => $business->id, 'is_exclusion' => true]];
        $this->actingAs($this->superAdmin())->postJson('/admin/campaigns/preview-targets', ['targets' => $targets])
            ->assertOk()->assertJsonPath('summary.screens', 1);
        $this->post('/admin/campaigns', $this->body(MediaAsset::factory()->image()->create(), $targets))->assertSessionHasNoErrors();
        $ids = app(ResolveCampaignTargets::class)->devicesFor(Campaign::query()->sole())->pluck('devices.id')->all();
        $this->assertSame([$included->id], $ids);
        $this->assertNotContains($excluded->id, $ids);
    }

    public function test_edit_round_trips_selectable_business_rules_and_preserves_unavailable_legacy_rules(): void
    {
        $business = Business::factory()->create(['category' => 'other']);
        $internal = Business::factory()->create(['slug' => Business::INTERNAL_SLUG, 'category' => 'other']);
        $targeted = Device::factory()->online()->create(['business_id' => $business->id]);
        Device::factory()->online()->create(['business_id' => $internal->id]);
        $campaign = Campaign::factory()->create(['status' => 'draft', 'starts_at' => today(), 'ends_at' => today()->addWeek()]);
        $media = MediaAsset::factory()->image()->create();
        $campaign->creatives()->create(['media_asset_id' => $media->id, 'duration' => 15, 'weight' => 1, 'position' => 0, 'status' => 'active']);
        foreach ([
            ['target_type' => 'business', 'target_id' => $business->id],
            ['target_type' => 'business', 'target_id' => $business->id, 'target_value' => 'legacy malformed'],
            ['target_type' => 'business', 'target_id' => $internal->id],
            ['target_type' => 'business', 'target_id' => 999999],
            ['target_type' => 'business', 'target_id' => null],
            ['target_type' => 'business_category', 'target_value' => 'restaurant', 'is_exclusion' => true],
        ] as $rule) {
            $campaign->targets()->create($rule);
        }
        $resolver = app(ResolveCampaignTargets::class);
        $before = $resolver->devicesFor($campaign)->orderBy('devices.id')->pluck('devices.id')->all();
        $response = $this->actingAs($this->superAdmin())->get("/admin/campaigns/{$campaign->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaign.has_legacy_targets', true)->has('campaign.targets', 1)
                ->where('campaign.targets.0.target_id', $business->id)->has('options.businesses', 1));
        $this->get("/admin/campaigns/{$campaign->id}")->assertInertia(fn ($page) => $page
            ->where('hasLegacyTargets', true)->where('targets.0.target_name', $business->name));
        $this->put("/admin/campaigns/{$campaign->id}", [...$this->body($media, $response->inertiaProps('campaign.targets')), 'advertiser_id' => $campaign->advertiser_id])
            ->assertSessionHasNoErrors();
        $this->assertSame($before, $resolver->devicesFor($campaign->fresh())->orderBy('devices.id')->pluck('devices.id')->all());
        $this->assertSame(5, $campaign->targets()->where('target_type', 'business')->count());
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business_category', 'target_value' => 'restaurant']);
        $this->put("/admin/campaigns/{$campaign->id}", [...$this->body($media, [['target_type' => 'device', 'target_id' => $targeted->id]]), 'advertiser_id' => $campaign->advertiser_id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $campaign->targets()->where('target_type', 'business')->where('target_id', $business->id)->count());
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business', 'target_id' => $business->id, 'target_value' => 'legacy malformed']);
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business', 'target_id' => $internal->id]);
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business', 'target_id' => 999999]);
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business', 'target_id' => null]);
    }

    public function test_hidden_exclusions_cannot_expand_a_targeted_campaign_to_the_whole_network(): void
    {
        $business = Business::factory()->create(['category' => 'other']);
        $target = Device::factory()->online()->create(['business_id' => $business->id]);
        Device::factory()->online()->create(['business_id' => Business::factory()->create(['category' => 'other'])->id]);
        $campaign = Campaign::factory()->create(['status' => 'draft', 'starts_at' => today(), 'ends_at' => today()->addWeek()]);
        $media = MediaAsset::factory()->image()->create();
        $campaign->creatives()->create(['media_asset_id' => $media->id, 'duration' => 15, 'weight' => 1, 'position' => 0, 'status' => 'active']);
        $campaign->targets()->create(['target_type' => 'business', 'target_id' => $business->id, 'is_exclusion' => false]);
        $campaign->targets()->create(['target_type' => 'business_category', 'target_value' => 'restaurant', 'is_exclusion' => true]);
        $before = app(ResolveCampaignTargets::class)->devicesFor($campaign)->pluck('devices.id')->all();
        $this->assertSame([$target->id], $before);
        $this->actingAs($this->superAdmin())->put("/admin/campaigns/{$campaign->id}", [
            ...$this->body($media, []), 'advertiser_id' => $campaign->advertiser_id,
        ])->assertSessionHasErrors('targets');
        $this->assertSame($before, app(ResolveCampaignTargets::class)->devicesFor($campaign->fresh())->pluck('devices.id')->all());
        $this->assertDatabaseHas('campaign_targets', ['campaign_id' => $campaign->id, 'target_type' => 'business', 'target_id' => $business->id]);
    }

    public function test_invalid_or_internal_business_targets_are_rejected_in_preview_and_save(): void
    {
        $internal = Business::factory()->create(['slug' => Business::INTERNAL_SLUG]);
        $body = $this->body(MediaAsset::factory()->image()->create(), []);
        $this->actingAs($this->superAdmin());
        foreach ([$internal->id, 999999, 0, -1] as $id) {
            $targets = [['target_type' => 'business', 'target_id' => $id]];
            $this->postJson('/admin/campaigns/preview-targets', ['targets' => $targets])->assertUnprocessable()->assertJsonValidationErrors('targets.0.target_id');
            $this->postJson('/admin/campaigns', [...$body, 'targets' => $targets])->assertUnprocessable()->assertJsonValidationErrors('targets.0.target_id');
        }
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_quick_play_targets_selected_businesses_and_retry_keeps_failed_device_subset(): void
    {
        $business = Business::factory()->create();
        $online = Device::factory()->online()->create(['business_id' => $business->id]);
        $offline = Device::factory()->create(['business_id' => $business->id, 'status' => 'offline', 'last_seen_at' => null]);
        $disabled = Device::factory()->create(['business_id' => $business->id, 'status' => 'disabled']);
        $other = Device::factory()->online()->create();
        $media = MediaAsset::factory()->image()->create();
        $options = $this->actingAs($this->superAdmin())->get('/admin/quick-play/create')->assertOk()->inertiaProps('options.businesses');
        $this->assertContains($business->id, array_column($options, 'id'));
        $this->assertEqualsCanonicalizing([$online->id, $offline->id], collect($options)->firstWhere('id', $business->id)['device_ids']);
        $this->post('/admin/quick-play', ['media_asset_id' => $media->id, 'scope' => 'businesses',
            'business_ids' => [$business->id], 'duration' => 10, 'display_mode' => 'fullscreen'])->assertSessionHasNoErrors();
        $quick = QuickPlay::query()->sole();
        $this->assertSame('businesses', $quick->scope->value);
        $this->assertSame(2, $quick->targets_count);
        $this->assertDatabaseHas('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $online->id, 'display_mode' => 'fullscreen']);
        $this->assertDatabaseHas('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $offline->id, 'status' => 'failed']);
        $this->assertDatabaseMissing('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $disabled->id]);
        $this->assertDatabaseMissing('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $other->id]);
        $quick->devices()->where('device_id', $online->id)->update(['status' => 'completed']);
        $quick->refreshProgress();
        $offline->markSeen();
        $this->post("/admin/quick-play/{$quick->id}/retry")->assertSessionHasNoErrors();
        $retry = QuickPlay::query()->whereKeyNot($quick->id)->sole();
        $this->assertSame(1, $retry->targets_count);
        $this->assertDatabaseHas('quick_play_devices', ['quick_play_id' => $retry->id, 'device_id' => $offline->id, 'display_mode' => 'fullscreen']);
        $this->assertDatabaseMissing('quick_play_devices', ['quick_play_id' => $retry->id, 'device_id' => $online->id]);
    }

    public function test_quick_play_rejects_missing_invalid_internal_business_and_non_fullscreen_modes(): void
    {
        $business = Business::factory()->create();
        $internal = Business::factory()->create(['slug' => Business::INTERNAL_SLUG]);
        $media = MediaAsset::factory()->image()->create();
        $payload = ['media_asset_id' => $media->id, 'scope' => 'businesses', 'duration' => 10, 'display_mode' => 'fullscreen'];
        $this->actingAs($this->superAdmin());
        $this->postJson('/admin/quick-play', $payload)->assertUnprocessable()->assertJsonValidationErrors('targets');
        foreach ([[$internal->id], [999999], [$business->id, $business->id]] as $ids) {
            $this->postJson('/admin/quick-play', [...$payload, 'business_ids' => $ids])->assertUnprocessable()->assertJsonValidationErrors('business_ids.0');
        }
        $this->postJson('/admin/quick-play', [...$payload, 'business_ids' => [$business->id], 'display_mode' => 'business'])
            ->assertUnprocessable()->assertJsonValidationErrors('display_mode');
        $this->assertDatabaseCount('quick_plays', 0);
        $this->postJson('/business/quick-play', $payload)->assertNotFound();
    }

    public function test_legacy_portal_account_cannot_target_a_business_from_admin_endpoints(): void
    {
        $business = Business::factory()->create();
        Device::factory()->online()->create(['business_id' => $business->id]);
        $media = MediaAsset::factory()->image()->create();
        $targets = [['target_type' => 'business', 'target_id' => $business->id]];
        $this->actingAs($this->businessUser($business));
        $this->postJson('/admin/campaigns/preview-targets', ['targets' => $targets])->assertForbidden();
        $this->postJson('/admin/campaigns', $this->body($media, $targets))->assertForbidden();
        $this->postJson('/admin/quick-play', ['media_asset_id' => $media->id, 'scope' => 'businesses',
            'business_ids' => [$business->id], 'duration' => 10, 'display_mode' => 'fullscreen'])->assertForbidden();
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('quick_plays', 0);
    }
}
