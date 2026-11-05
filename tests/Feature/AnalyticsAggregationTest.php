<?php

namespace Tests\Feature;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Analytics\Actions\AggregateDailyAnalytics;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Playback\Models\PlaybackEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_aggregation_builds_campaign_device_business_and_city_stats(): void
    {
        $location = Location::factory()->create(['city' => 'Bogotá']);
        $business = $location->business;

        $device = Device::factory()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'status' => DeviceStatus::Online,
        ]);

        $media = MediaAsset::factory()->create();
        $campaign = Campaign::factory()->create(['advertiser_id' => Advertiser::factory()]);
        $creative = $campaign->creatives()->create([
            'media_asset_id' => $media->id,
            'duration' => 10,
            'weight' => 10,
            'status' => 'active',
        ]);

        $startedAt = now()->setTime(10, 0, 0);

        PlaybackEvent::query()->insert([
            [
                'device_id' => $device->id,
                'campaign_id' => $campaign->id,
                'creative_id' => $creative->id,
                'media_asset_id' => $media->id,
                'started_at' => $startedAt,
                'completed_at' => $startedAt->copy()->addSeconds(10),
                'duration_played' => 10,
                'completed' => true,
                'error_code' => null,
                'manifest_version' => 20260912120000,
                'created_at' => $startedAt,
            ],
            [
                'device_id' => $device->id,
                'campaign_id' => $campaign->id,
                'creative_id' => $creative->id,
                'media_asset_id' => $media->id,
                'started_at' => $startedAt->copy()->addMinute(),
                'completed_at' => null,
                'duration_played' => 4,
                'completed' => false,
                'error_code' => 'NETWORK_TIMEOUT',
                'manifest_version' => 20260912120000,
                'created_at' => $startedAt->copy()->addMinute(),
            ],
        ]);

        app(AggregateDailyAnalytics::class)->handle(today());

        $this->assertDatabaseHas('campaign_daily_stats', [
            'campaign_id' => $campaign->id,
            'playbacks_count' => 2,
            'completed_count' => 1,
            'failures' => 1,
        ]);

        $this->assertDatabaseHas('device_daily_stats', [
            'device_id' => $device->id,
            'playbacks_count' => 2,
        ]);

        $this->assertDatabaseHas('business_daily_stats', [
            'business_id' => $business->id,
            'playbacks_count' => 2,
        ]);

        $this->assertDatabaseHas('city_daily_stats', [
            'city' => 'Bogotá',
            'playbacks_count' => 2,
        ]);
    }
}
