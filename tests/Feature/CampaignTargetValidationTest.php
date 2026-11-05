<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Enums\CampaignTargetType;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CampaignTargetValidationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function campaign(): Campaign
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft, 'starts_at' => today(), 'ends_at' => today()->addWeek(),
        ]);
        $campaign->creatives()->create([
            'media_asset_id' => MediaAsset::factory()->create()->id,
            'duration' => 30, 'weight' => 10, 'position' => 0, 'status' => 'active',
        ]);
        $campaign->targets()->create(['target_type' => 'city', 'target_value' => 'Bogotá']);

        return $campaign;
    }

    private function payload(Campaign $campaign): array
    {
        return [
            'advertiser_id' => $campaign->advertiser_id, 'name' => 'Campaña editada',
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addWeek()->toDateString(), 'priority' => 5,
            'creatives' => [['media_asset_id' => $campaign->creatives()->sole()->media_asset_id, 'duration' => 30, 'weight' => 10]],
        ];
    }

    public function test_edit_form_round_trips_every_target_type_and_preserves_exclusions(): void
    {
        $campaign = $this->campaign();
        $campaign->targets()->delete();
        $location = Location::factory()->create();
        $device = Device::factory()->online()->create(['business_id' => $location->business_id, 'location_id' => $location->id]);
        $expected = [];
        foreach (CampaignTargetType::cases() as $type) {
            $target = [
                'target_type' => $type->value,
                'target_id' => match ($type) {
                    CampaignTargetType::Business => $location->business_id,
                    CampaignTargetType::Location => $location->id,
                    CampaignTargetType::Device => $device->id,
                    default => null,
                },
                'target_value' => $type->isEntity() ? null : 'segmento',
                'is_exclusion' => $type === CampaignTargetType::Device,
            ];
            $campaign->targets()->create($target);
            $expected[] = $target;
        }
        $expected = collect($expected)->sortBy('target_type')->values()->all();
        $this->actingAs($this->superAdmin());
        $targets = $this->get("/admin/campaigns/{$campaign->id}/edit")->assertOk()->inertiaProps('campaign.targets');
        $this->assertSame($expected, collect($targets)->sortBy('target_type')->values()->all());
        $this->put("/admin/campaigns/{$campaign->id}", [...$this->payload($campaign), 'targets' => $targets])
            ->assertRedirect()->assertSessionHasNoErrors();
        $saved = $campaign->targets()->orderBy('id')->get()->map(fn ($target) => [
            'target_type' => $target->target_type->value, 'target_id' => $target->target_id,
            'target_value' => $target->target_value, 'is_exclusion' => $target->is_exclusion,
        ])->sortBy('target_type')->values()->all();
        $this->assertSame($expected, $saved);
        $this->get("/admin/campaigns/{$campaign->id}")->assertInertia(fn ($page) => $page
            ->where('targets.0.target_type.value', 'business')->where('targets.0.target_type.label', 'Negocio'));
    }

    public function test_an_edited_campaign_can_be_published_using_the_form_target_payload(): void
    {
        $campaign = $this->campaign();
        $location = Location::factory()->create(['city' => 'Bogotá']);
        Device::factory()->online()->create(['business_id' => $location->business_id, 'location_id' => $location->id]);
        $this->actingAs($this->superAdmin());
        $targets = $this->get("/admin/campaigns/{$campaign->id}/edit")->assertOk()->inertiaProps('campaign.targets');
        $this->put("/admin/campaigns/{$campaign->id}", [...$this->payload($campaign), 'targets' => $targets, 'publish' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->target_screen_count);
    }

    #[DataProvider('invalidTargets')]
    public function test_invalid_targets_return_field_errors_without_mutating_campaigns(mixed $targets, string $field): void
    {
        $campaign = $this->campaign();
        $originalName = $campaign->name;
        $originalTargets = $campaign->targets()->get()->toArray();
        $body = [...$this->payload($campaign), 'targets' => $targets];
        $this->actingAs($this->superAdmin());
        $this->postJson('/admin/campaigns', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson("/admin/campaigns/{$campaign->id}", $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('campaigns', 1);
        $this->assertSame($originalName, $campaign->fresh()->name);
        $this->assertSame($originalTargets, $campaign->targets()->get()->toArray());
    }

    public static function invalidTargets(): array
    {
        return [
            'presenter object' => [[['target_type' => ['value' => 'city', 'label' => 'Ciudad'], 'target_value' => 'Bogotá']], 'targets.0.target_type'],
            'nested type array' => [[['target_type' => [['value' => 'city']]]], 'targets.0.target_type'],
            'null collection' => [null, 'targets'],
            'string collection' => ['city', 'targets'],
            'boolean collection' => [false, 'targets'],
            'scalar row' => [['city'], 'targets.0'],
            'null row' => [[null], 'targets.0'],
            'missing type' => [[['target_value' => 'Bogotá']], 'targets.0.target_type'],
            'null type' => [[['target_type' => null]], 'targets.0.target_type'],
            'boolean type' => [[['target_type' => true]], 'targets.0.target_type'],
            'numeric type' => [[['target_type' => 1]], 'targets.0.target_type'],
            'unknown type' => [[['target_type' => 'invalid']], 'targets.0.target_type'],
        ];
    }

    public function test_valid_target_types_still_require_their_id_or_value(): void
    {
        $campaign = $this->campaign();
        $targets = array_map(fn ($type) => ['target_type' => $type->value], CampaignTargetType::cases());
        $errors = [];
        foreach (CampaignTargetType::cases() as $index => $type) {
            $errors[] = "targets.{$index}.".($type->isEntity() ? 'target_id' : 'target_value');
        }
        $this->actingAs($this->superAdmin())->postJson('/admin/campaigns', [...$this->payload($campaign), 'targets' => $targets])
            ->assertUnprocessable()->assertJsonValidationErrors($errors);
    }

    public function test_browser_submission_with_an_object_type_returns_validation_errors(): void
    {
        $campaign = $this->campaign();
        $url = "/admin/campaigns/{$campaign->id}/edit";
        $this->actingAs($this->superAdmin())->from($url)->put("/admin/campaigns/{$campaign->id}", [
            ...$this->payload($campaign),
            'targets' => [['target_type' => ['value' => 'city', 'label' => 'Ciudad'], 'target_value' => 'Bogotá']],
        ])->assertRedirect($url)->assertSessionHasErrors('targets.0.target_type');
    }
}
