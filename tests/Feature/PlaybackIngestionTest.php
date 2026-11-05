<?php

namespace Tests\Feature;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaybackIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_can_ingest_a_playback_batch(): void
    {
        $device = Device::factory()->create();
        $token = $device->issueToken();

        $media = MediaAsset::factory()->create();
        $campaign = Campaign::factory()->create(['advertiser_id' => Advertiser::factory()]);
        $creative = $campaign->creatives()->create([
            'media_asset_id' => $media->id,
            'duration' => 10,
            'weight' => 10,
            'status' => 'active',
        ]);

        $startedAt = now()->subMinutes(5);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/playback-events/batch', [
                'events' => [
                    [
                        'media_asset_id' => $media->id,
                        'campaign_id' => $campaign->id,
                        'creative_id' => $creative->id,
                        'started_at' => $startedAt->toIso8601String(),
                        'completed_at' => $startedAt->copy()->addSeconds(10)->toIso8601String(),
                        'duration_played' => 10,
                        'completed' => true,
                        'manifest_version' => 20260912120000,
                    ],
                    [
                        'media_asset_id' => $media->id,
                        'started_at' => $startedAt->toIso8601String(),
                        'duration_played' => 3,
                        'completed' => false,
                        'error_code' => 'DECODE_ERROR',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ingested' => 2]);

        $this->assertDatabaseCount('playback_events', 2);
        $this->assertDatabaseHas('playback_events', [
            'device_id' => $device->id,
            'campaign_id' => $campaign->id,
            'completed' => true,
        ]);
        $this->assertDatabaseHas('playback_events', [
            'device_id' => $device->id,
            'error_code' => 'DECODE_ERROR',
        ]);
    }

    public function test_batches_require_authentication(): void
    {
        $this->postJson('/api/v1/device/playback-events/batch', ['events' => []])
            ->assertUnauthorized();
    }

    public function test_events_must_reference_valid_media(): void
    {
        $device = Device::factory()->create();
        $token = $device->issueToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/playback-events/batch', [
                'events' => [
                    ['media_asset_id' => 999999, 'started_at' => now()->toIso8601String()],
                ],
            ])
            ->assertStatus(422);
    }
}
