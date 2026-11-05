<?php

namespace Tests\Feature;

use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Enums\DeviceCommandStatus;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Models\Device;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DeviceCommandTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_an_administrator_can_queue_a_device_command(): void
    {
        $device = Device::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post("/admin/devices/{$device->id}/commands", ['command' => 'SYNC_CONTENT'])
            ->assertRedirect();

        $this->assertDatabaseHas('device_commands', [
            'device_id' => $device->id,
            'command' => 'SYNC_CONTENT',
            'status' => 'pending',
        ]);
    }

    public function test_devices_poll_and_complete_commands(): void
    {
        $device = Device::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post("/admin/devices/{$device->id}/commands", ['command' => 'REFRESH_MANIFEST'])
            ->assertRedirect();

        $token = $device->issueToken();
        $headers = ['Authorization' => "Bearer {$token}"];

        $list = $this->withHeaders($headers)->getJson('/api/v1/device/commands');
        $list->assertOk();
        $commandId = $list->json('commands.0.id');

        $this->assertNotNull($commandId);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $list->json('commands.0.expires_at'));
        $this->assertDatabaseHas('device_commands', ['id' => $commandId, 'status' => 'sent']);

        $this->withHeaders($headers)
            ->postJson("/api/v1/device/commands/{$commandId}/result", [
                'status' => 'completed',
                'result' => ['version' => '20260912130000'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_commands', ['id' => $commandId, 'status' => 'completed']);
    }

    public function test_a_device_cannot_report_results_for_another_device(): void
    {
        $device = Device::factory()->create();
        $other = Device::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post("/admin/devices/{$device->id}/commands", ['command' => 'MUTE']);

        $command = $device->commands()->firstOrFail();
        $token = $other->issueToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/device/commands/{$command->id}/result", ['status' => 'completed'])
            ->assertNotFound();
    }

    public function test_pending_quick_play_dates_are_normalized_without_rewriting_the_command(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17T20:05:00Z'));
        $device = Device::factory()->create();
        $payload = ['expires_at' => '2026-09-17T15:35:00-05:00', 'quick_play_device_id' => 123];
        $command = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::QuickPlay, $payload);

        $this->withToken($device->issueToken())->getJson('/api/v1/device/commands')
            ->assertOk()
            ->assertJsonPath('commands.0.id', $command->id)
            ->assertJsonPath('commands.0.expires_at', '2026-09-17T20:35:00Z')
            ->assertJsonPath('commands.0.payload.expires_at', '2026-09-17T20:35:00Z')
            ->assertJsonPath('commands.0.payload.quick_play_device_id', 123);

        $this->assertSame($payload, $command->fresh()->payload);
    }

    public function test_invalid_payload_date_does_not_block_delivery_of_other_commands(): void
    {
        $device = Device::factory()->create();
        app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::QuickPlay, ['expires_at' => 'invalid-date']);
        app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::Mute);

        $this->withToken($device->issueToken())->getJson('/api/v1/device/commands')
            ->assertOk()->assertJsonCount(2, 'commands')
            ->assertJsonPath('commands.0.payload.expires_at', 'invalid-date')
            ->assertJsonPath('commands.1.command', 'MUTE');
    }

    public function test_issuing_a_command_requires_permission(): void
    {
        $device = Device::factory()->create();
        $support = $this->userWithRole(RoleEnum::Support);

        $this->actingAs($support)
            ->post("/admin/devices/{$device->id}/commands", ['command' => 'CLEAR_CACHE'])
            ->assertForbidden();
    }

    public function test_a_lost_poll_response_is_retried_with_the_same_command_and_expiry(): void
    {
        $this->freezeTime();
        $device = Device::factory()->create();
        $command = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::QuickPlay, [
            'quick_play_device_id' => 123,
            'expires_at' => now()->addMinutes(30)->toIso8601ZuluString(),
        ]);
        $this->withToken($device->issueToken());

        $original = $this->getJson('/api/v1/device/commands')->assertOk()->json('commands.0');
        $this->getJson('/api/v1/device/commands')->assertOk()->assertJsonCount(0, 'commands');

        // The first HTTP response never reached the TV. Offer the same ID again.
        $this->travel(60)->seconds();
        $retry = $this->getJson('/api/v1/device/commands')->assertOk()->json('commands.0');
        $this->assertSame($original, $retry);
        $this->assertSame(1, $device->commands()->count());

        $this->postJson("/api/v1/device/commands/{$command->id}/result", ['status' => 'completed'])->assertOk();
        $this->travel(60)->seconds();
        $this->getJson('/api/v1/device/commands')->assertOk()->assertJsonCount(0, 'commands');
    }

    public function test_retries_do_not_deliver_expired_terminal_or_other_device_commands(): void
    {
        $this->freezeTime();
        $device = Device::factory()->create();
        foreach ([DeviceCommandStatus::Completed, DeviceCommandStatus::Failed, DeviceCommandStatus::Expired, DeviceCommandStatus::Sent] as $status) {
            $command = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::QuickPlay);
            $command->forceFill([
                'status' => $status,
                'sent_at' => now()->subMinutes(2),
                'expires_at' => $status === DeviceCommandStatus::Sent ? now() : now()->addMinutes(30),
            ])->save();
        }
        $other = app(IssueDeviceCommand::class)->handle(Device::factory()->create(), DeviceCommandType::QuickPlay);
        $other->forceFill(['status' => DeviceCommandStatus::Sent, 'sent_at' => now()->subMinutes(2)])->save();

        $this->withToken($device->issueToken())->getJson('/api/v1/device/commands')
            ->assertOk()->assertJsonCount(0, 'commands');
    }

    public function test_retries_do_not_starve_new_commands_or_commands_beyond_the_first_batch(): void
    {
        $this->freezeTime();
        $device = Device::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            $command = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::QuickPlay);
            $command->forceFill(['status' => DeviceCommandStatus::Sent, 'sent_at' => now()->subMinutes(2)])->save();
        }
        $new = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::Mute);
        $this->withToken($device->issueToken())->getJson('/api/v1/device/commands')
            ->assertOk()->assertJsonCount(20, 'commands')->assertJsonPath('commands.0.id', $new->id);

        $this->getJson('/api/v1/device/commands')->assertOk()->assertJsonCount(2, 'commands');
    }
}
