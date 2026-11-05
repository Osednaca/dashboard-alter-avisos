<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CampaignEditingTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_editor_loads_saved_fields_and_saves_without_reselecting_the_advertiser(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft, 'description' => 'Descripción guardada',
            'starts_at' => today(), 'ends_at' => today()->addMonth(),
            'daily_start_time' => '09:15:00', 'daily_end_time' => '21:45:00',
            'days_of_week' => [2, 4, 7], 'priority' => 8,
            'playback_goal' => 1234, 'impressions_goal' => 5678, 'budget' => '32100.50',
        ]);
        $second = $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->create()->id,
            'duration' => 600, 'weight' => 20, 'position' => 1, 'status' => 'active']);
        $first = $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->create()->id,
            'duration' => 900, 'weight' => 30, 'position' => 0, 'status' => 'active']);
        $campaign->targets()->create(['target_type' => 'city', 'target_value' => 'Bogotá', 'is_exclusion' => false]);
        $this->actingAs($this->superAdmin());
        $response = $this->get("/admin/campaigns/{$campaign->id}/edit")->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('campaign.advertiser.id', $campaign->advertiser_id)
            ->where('campaign.description', 'Descripción guardada')
            ->where('campaign.starts_at', today()->toDateString())
            ->where('campaign.ends_at', today()->addMonth()->toDateString())
            ->where('campaign.daily_start_time', '09:15:00')->where('campaign.daily_end_time', '21:45:00')
            ->where('campaign.days_of_week', [2, 4, 7])->where('campaign.priority', 8)
            ->where('campaign.playback_goal', 1234)->where('campaign.impressions_goal', 5678)
            ->where('campaign.budget', '32100.50')
            ->where('campaign.creatives.0.media_asset_id', $first->media_asset_id)
            ->where('campaign.creatives.1.media_asset_id', $second->media_asset_id));
        $data = $response->inertiaProps('campaign');
        $data['advertiser_id'] = $data['advertiser']['id'];
        $data['daily_start_time'] = substr($data['daily_start_time'], 0, 5);
        $data['daily_end_time'] = substr($data['daily_end_time'], 0, 5);
        $data['publish'] = false;
        $this->put("/admin/campaigns/{$campaign->id}", $data)->assertRedirect()->assertSessionHasNoErrors();
        $saved = $campaign->fresh();
        $this->assertSame($campaign->advertiser_id, $saved->advertiser_id);
        foreach (['description', 'days_of_week', 'priority', 'playback_goal', 'impressions_goal', 'budget'] as $field) {
            $this->assertSame($campaign->$field, $saved->$field, $field);
        }
        $this->assertSame([$first->media_asset_id, $second->media_asset_id], $saved->creatives()->orderBy('position')->pluck('media_asset_id')->all());
        $this->assertSame([900, 600], $saved->creatives()->orderBy('position')->pluck('duration')->all());
    }

    public function test_manifest_keeps_campaign_blocks_and_uses_saved_creative_positions(): void
    {
        $device = Device::factory()->online()->create();
        $campaigns = [];
        $creatives = [];
        foreach ([5, 5, 9] as $priority) {
            $campaign = Campaign::factory()->create(['status' => CampaignStatus::Active,
                'starts_at' => today(), 'ends_at' => today()->addWeek(), 'priority' => $priority]);
            $campaign->targets()->create(['target_type' => 'device', 'target_id' => $device->id]);
            $second = $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->create()->id,
                'duration' => 20, 'weight' => 1, 'position' => 1, 'status' => 'active']);
            $first = $campaign->creatives()->create(['media_asset_id' => MediaAsset::factory()->create()->id,
                'duration' => 30, 'weight' => 1, 'position' => 0, 'status' => 'active']);
            $campaigns[] = $campaign;
            $creatives[$campaign->id] = [$first->id, $second->id];
        }
        $payload = app(BuildDeviceManifest::class)->handle($device)->payload['advertising_playlist']['campaigns'];
        $this->assertSame([$campaigns[2]->id, $campaigns[0]->id, $campaigns[1]->id], array_column($payload, 'id'));
        foreach ($payload as $campaign) {
            $this->assertSame($creatives[$campaign['id']], array_column($campaign['creatives'], 'creative_id'));
        }
    }
}
