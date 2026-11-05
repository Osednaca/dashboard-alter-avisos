<?php

namespace Tests\Feature;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\Layout;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Enums\QuickPlayStatus;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class QuickPlayTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_a_quick_play_is_sent_to_devices_without_creating_a_campaign(): void
    {
        $location = Location::factory()->create();
        $online = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $online2 = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $media = MediaAsset::factory()->image()->create();

        $this->actingAs($this->superAdmin())
            ->post('/admin/quick-play', [
                'media_asset_id' => $media->id,
                'display_mode' => 'advertising',
                'scope' => 'devices',
                'duration' => 12,
                'device_ids' => [$online->id, $online2->id],
            ])
            ->assertRedirect();

        $quickPlay = QuickPlay::query()->firstOrFail();

        $this->assertSame(2, $quickPlay->targets_count);
        $this->assertSame(QuickPlayStatus::Sending, $quickPlay->status);
        $this->assertDatabaseCount('quick_play_devices', 2);
        $this->assertDatabaseCount('device_commands', 2);
        $this->assertDatabaseHas('device_commands', ['command' => 'QUICK_PLAY']);
        // Quick play never creates a campaign.
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_offline_devices_are_reported_as_not_delivered(): void
    {
        $location = Location::factory()->create();
        Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $offline = Device::factory()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
            'status' => DeviceStatus::Offline,
            'last_seen_at' => now()->subHours(3),
        ]);

        $media = MediaAsset::factory()->image()->create();

        $quickPlay = app(StartQuickPlay::class)->handle(
            $this->superAdmin(),
            $media,
            QuickPlayDisplayMode::Fullscreen,
            QuickPlayScope::All,
            10,
        );

        $this->assertSame(2, $quickPlay->targets_count);
        $this->assertSame(1, $quickPlay->failed_count);
        $this->assertDatabaseHas('quick_play_devices', [
            'quick_play_id' => $quickPlay->id,
            'device_id' => $offline->id,
            'status' => QuickPlayDeviceStatus::Failed->value,
        ]);
        // The reachable device received a command; the offline one did not.
        $this->assertDatabaseCount('device_commands', 1);
    }

    public function test_fullscreen_takeover_snapshots_the_previous_layout(): void
    {
        $layout = Layout::query()->create([
            'name' => 'Test layout',
            'orientation' => 'landscape',
            'business_percentage' => 70,
            'advertising_percentage' => 30,
            'is_default' => true,
        ]);

        $location = Location::factory()->create();
        $device = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
            'current_layout_id' => $layout->id,
        ]);

        $media = MediaAsset::factory()->image()->create();

        $quickPlay = app(StartQuickPlay::class)->handle(
            $this->superAdmin(),
            $media,
            QuickPlayDisplayMode::Fullscreen,
            QuickPlayScope::Devices,
            15,
            ['device_ids' => [$device->id]],
        );

        $this->assertDatabaseHas('quick_play_devices', [
            'quick_play_id' => $quickPlay->id,
            'device_id' => $device->id,
            'previous_layout_id' => $layout->id,
            'status' => QuickPlayDeviceStatus::Pending->value,
        ]);
    }

    public function test_devices_report_quick_play_progress_and_completion(): void
    {
        $location = Location::factory()->create();
        $device = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $media = MediaAsset::factory()->video()->create();

        $quickPlay = app(StartQuickPlay::class)->handle(
            $this->superAdmin(),
            $media,
            QuickPlayDisplayMode::Advertising,
            QuickPlayScope::Devices,
            null,
            ['device_ids' => [$device->id]],
        );

        $row = $quickPlay->devices()->firstOrFail();
        $token = $device->issueToken();

        $command = $device->commands()->sole();
        $this->assertSame($quickPlay->expires_at->toIso8601ZuluString(), $command->payload['expires_at']);
        $this->withToken($token)->getJson('/api/v1/device/commands')
            ->assertOk()
            ->assertJsonPath('commands.0.command', 'QUICK_PLAY')
            ->assertJsonPath('commands.0.expires_at', $command->expires_at->toIso8601ZuluString())
            ->assertJsonPath('commands.0.payload.expires_at', $quickPlay->expires_at->toIso8601ZuluString());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/quick-play/status', [
                'quick_play_device_id' => $row->id,
                'status' => 'playing',
            ])
            ->assertOk();

        $this->assertDatabaseHas('quick_play_devices', ['id' => $row->id, 'status' => 'playing']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/quick-play/status', [
                'quick_play_device_id' => $row->id,
                'status' => 'completed',
            ])
            ->assertOk();

        $quickPlay->refresh();
        $this->assertSame(QuickPlayStatus::Completed, $quickPlay->status);
        $this->assertSame(1, $quickPlay->delivered_count);
    }

    public function test_a_device_cannot_report_another_devices_quick_play(): void
    {
        $location = Location::factory()->create();
        $device = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $other = Device::factory()->online()->create([
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
        $media = MediaAsset::factory()->image()->create();

        $quickPlay = app(StartQuickPlay::class)->handle(
            $this->superAdmin(),
            $media,
            QuickPlayDisplayMode::Business,
            QuickPlayScope::Devices,
            10,
            ['device_ids' => [$device->id]],
        );

        $row = $quickPlay->devices()->firstOrFail();
        $token = $other->issueToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/quick-play/status', [
                'quick_play_device_id' => $row->id,
                'status' => 'playing',
            ])
            ->assertNotFound();
    }

    public function test_sending_a_quick_play_requires_permission(): void
    {
        $media = MediaAsset::factory()->image()->create();
        $support = $this->userWithRole(RoleEnum::Support);

        $this->actingAs($support)
            ->post('/admin/quick-play', [
                'media_asset_id' => $media->id,
                'display_mode' => 'advertising',
                'scope' => 'all',
                'duration' => 10,
            ])
            ->assertForbidden();
    }
}
