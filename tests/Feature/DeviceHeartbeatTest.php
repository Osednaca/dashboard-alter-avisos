<?php

namespace Tests\Feature;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_can_send_a_heartbeat(): void
    {
        $device = Device::factory()->create(['status' => DeviceStatus::Offline]);
        $token = $device->issueToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/heartbeat', [
                'app_version' => '1.6.3',
                'available_storage' => 5_000_000_000,
                'storage_total' => 32_000_000_000,
                'manifest_version' => '20260912120000',
                'player_status' => 'playing',
                'network_status' => 'connected',
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('device_heartbeats', ['device_id' => $device->id]);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame('1.6.3', $device->app_version);
        $this->assertSame(5_000_000_000, $device->storage_free);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_heartbeat_requires_a_valid_device_token(): void
    {
        $this->postJson('/api/v1/device/heartbeat', [])->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/device/heartbeat', [])
            ->assertUnauthorized();
    }

    public function test_revoked_tokens_are_rejected(): void
    {
        $device = Device::factory()->create();
        $token = $device->issueToken();
        $device->revokeToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/heartbeat', [])
            ->assertUnauthorized();
    }
}
