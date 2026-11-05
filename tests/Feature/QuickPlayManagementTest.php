<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Actions\IssueDeviceCommand;
use App\Domain\Devices\Enums\DeviceCommandType;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Actions\UpdateQuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDeviceStatus;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class QuickPlayManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function delivery(?Business $business = null): array
    {
        $user = $business ? $this->businessUser($business) : $this->superAdmin();
        $device = Device::factory()->online()->create($business ? ['business_id' => $business->id] : []);
        $media = MediaAsset::factory()->image()->create($business ? ['owner_type' => $business->getMorphClass(), 'owner_id' => $business->id] : []);
        $quick = app(StartQuickPlay::class)->handle($user, $media, $business ? QuickPlayDisplayMode::Business : QuickPlayDisplayMode::Fullscreen,
            QuickPlayScope::Devices, 12, ['device_ids' => [$device->id]], $business);

        return [$quick, $device, $media, $user];
    }

    private function markFailed(QuickPlay $quick): void
    {
        app(UpdateQuickPlayDeviceStatus::class)->handle($quick->devices()->sole(), QuickPlayDeviceStatus::Failed, 'Archivo no disponible');
    }

    public function test_retry_only_targets_failed_screens_with_new_commands_and_expiry_and_is_idempotent(): void
    {
        $this->freezeTime();
        $user = $this->superAdmin();
        $media = MediaAsset::factory()->image()->create();
        $failed = Device::factory()->online()->create();
        $completed = Device::factory()->online()->create();
        $quick = app(StartQuickPlay::class)->handle($user, $media, QuickPlayDisplayMode::Advertising,
            QuickPlayScope::All, 17);
        foreach ($quick->devices as $row) {
            app(UpdateQuickPlayDeviceStatus::class)->handle($row,
                $row->device_id === $failed->id ? QuickPlayDeviceStatus::Failed : QuickPlayDeviceStatus::Completed);
        }
        $oldCommand = $quick->devices()->where('device_id', $failed->id)->sole()->command_id;
        $this->travel(31)->minutes();
        $failed->forceFill(['last_seen_at' => now()])->save();
        $this->actingAs($user)->post('/admin/quick-play/'.$quick->id.'/retry')->assertSessionHasNoErrors()->assertRedirect();
        $retry = QuickPlay::query()->latest('id')->firstOrFail();
        $this->assertNotSame($quick->id, $retry->id);
        $this->assertSame($quick->id, $retry->metadata['retry_of_id']);
        $this->assertSame(17, $retry->duration);
        $this->assertSame(QuickPlayDisplayMode::Advertising, $retry->display_mode);
        $this->assertSame($media->id, $retry->media_asset_id);
        $this->assertTrue($retry->expires_at->isFuture());
        $row = $retry->devices()->sole();
        $this->assertSame($failed->id, $row->device_id);
        $this->assertNotSame($oldCommand, $row->command_id);
        $this->assertSame($row->id, $row->command->payload['quick_play_device_id']);
        $this->assertSame($retry->expires_at->toIso8601ZuluString(), $row->command->payload['expires_at']);
        $this->post('/admin/quick-play/'.$quick->id.'/retry')->assertRedirect('/admin/quick-play/'.$retry->id);
        $this->assertDatabaseCount('quick_plays', 2);
        $this->assertDatabaseCount('device_commands', 3);
        $this->assertDatabaseHas('quick_play_devices', ['quick_play_id' => $quick->id, 'device_id' => $completed->id, 'status' => 'completed']);
        $this->get('/admin/quick-play/'.$quick->id)->assertInertia(fn ($page) => $page
            ->where('quickPlay.can_retry', false)->where('quickPlay.retry_id', $retry->id));
    }

    public function test_retry_rejects_running_completed_unready_or_unavailable_deliveries(): void
    {
        [$quick, $device, $media, $user] = $this->delivery();
        $url = '/admin/quick-play/'.$quick->id.'/retry';
        $this->actingAs($user)->post($url)->assertSessionHasErrors('quick_play');
        $this->markFailed($quick);
        $media->update(['processing_status' => 'pending']);
        $this->post($url)->assertSessionHasErrors('media_asset_id');
        $media->update(['processing_status' => 'ready']);
        $device->update(['status' => DeviceStatus::Disabled]);
        $this->post($url)->assertSessionHasErrors('targets');
        $this->assertDatabaseCount('quick_plays', 1);
        $this->assertDatabaseCount('device_commands', 1);

        [$completed, , , $user] = $this->delivery();
        app(UpdateQuickPlayDeviceStatus::class)->handle($completed->devices()->sole(), QuickPlayDeviceStatus::Completed);
        $this->actingAs($user)->post('/admin/quick-play/'.$completed->id.'/retry')->assertSessionHasErrors('quick_play');
    }

    public function test_an_offline_failure_without_a_command_can_be_retried_after_reconnection(): void
    {
        $user = $this->superAdmin();
        $device = Device::factory()->create(['status' => DeviceStatus::Offline, 'last_seen_at' => now()->subHours(2)]);
        $media = MediaAsset::factory()->image()->create();
        $quick = app(StartQuickPlay::class)->handle($user, $media, QuickPlayDisplayMode::Business,
            QuickPlayScope::Devices, 10, ['device_ids' => [$device->id]]);
        $this->assertTrue($quick->canRetry());
        $this->assertNull($quick->devices()->sole()->command_id);
        $device->forceFill(['status' => DeviceStatus::Online, 'last_seen_at' => now()])->save();
        $this->actingAs($user)->post('/admin/quick-play/'.$quick->id.'/retry')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('device_commands', 1);
        $retry = QuickPlay::query()->latest('id')->firstOrFail();
        $this->assertSame(QuickPlayDeviceStatus::Pending, $retry->devices()->sole()->status);
    }

    public function test_delete_hides_history_cancels_commands_and_accepts_late_device_callbacks(): void
    {
        [$quick, $device, $media, $user] = $this->delivery();
        $token = $device->issueToken();
        $row = $quick->devices()->sole();
        $this->withToken($token)->getJson('/api/v1/device/commands')->assertOk()->assertJsonCount(1, 'commands');
        $unrelated = app(IssueDeviceCommand::class)->handle($device, DeviceCommandType::Mute);
        $this->actingAs($user)->delete('/admin/quick-play/'.$quick->id)->assertRedirect('/admin/quick-play');
        $this->assertSoftDeleted('quick_plays', ['id' => $quick->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
        $this->assertDatabaseHas('device_commands', ['id' => $row->command_id, 'status' => 'failed']);
        $this->get('/admin/quick-play')->assertInertia(fn ($page) => $page->has('quickPlays.data', 0));
        $this->get('/admin/quick-play/'.$quick->id)->assertNotFound();
        $this->post('/admin/quick-play/'.$quick->id.'/retry')->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/device/commands')->assertOk()
            ->assertJsonCount(1, 'commands')->assertJsonPath('commands.0.id', $unrelated->id);
        $this->postJson('/api/v1/device/quick-play/status', ['quick_play_device_id' => $row->id, 'status' => 'playing'])->assertOk();
        $this->assertDatabaseHas('device_commands', ['id' => $row->command_id, 'status' => 'failed']);
        $this->postJson('/api/v1/device/commands/'.$row->command_id.'/result', ['status' => 'completed'])->assertOk();
        $this->assertSoftDeleted('quick_plays', ['id' => $quick->id]);
    }

    public function test_business_can_retry_and_delete_its_own_history_with_ownership_preserved(): void
    {
        $business = Business::factory()->create();
        [$quick, $device, , $user] = $this->delivery($business);
        $this->markFailed($quick);
        $this->actingAs($user)->post('/business/quick-play/'.$quick->id.'/retry')->assertSessionHasNoErrors();
        $retry = QuickPlay::query()->latest('id')->firstOrFail();
        $this->assertSame($business->id, $retry->business_id);
        $this->assertSame($device->id, $retry->devices()->sole()->device_id);
        $this->delete('/business/quick-play/'.$retry->id)->assertRedirect('/business/quick-play');
        $this->assertSoftDeleted('quick_plays', ['id' => $retry->id]);
        $this->assertNotNull($quick->fresh());
    }

    public function test_business_cannot_retry_or_delete_another_business_even_with_membership_in_both(): void
    {
        $business = Business::factory()->create();
        [$quick, , , $user] = $this->delivery($business);
        $this->markFailed($quick);
        $other = Business::factory()->create();
        $other->users()->attach($user, ['role' => 'owner', 'is_primary' => false]);
        $this->actingAs($user)->withSession(['business_id' => $other->id]);
        $this->post('/business/quick-play/'.$quick->id.'/retry')->assertNotFound();
        $this->delete('/business/quick-play/'.$quick->id)->assertNotFound();
        $this->assertDatabaseCount('quick_plays', 1);
        $this->assertNotNull($quick->fresh());
    }

    public function test_business_cannot_retry_legacy_advertising_or_fullscreen_deliveries(): void
    {
        [$quick, , , $user] = $this->delivery(Business::factory()->create());
        $this->markFailed($quick);
        $this->actingAs($user);
        foreach ([QuickPlayDisplayMode::Advertising, QuickPlayDisplayMode::Fullscreen] as $mode) {
            $quick->update(['display_mode' => $mode]);
            $this->get('/business/quick-play/'.$quick->id)->assertInertia(fn ($page) => $page->where('quickPlay.can_retry', false));
            $this->post('/business/quick-play/'.$quick->id.'/retry')->assertSessionHasErrors('quick_play');
        }
        $this->assertDatabaseCount('quick_plays', 1);
        $this->assertDatabaseCount('device_commands', 1);
    }

    public function test_business_dispatch_rejects_forbidden_modes_and_scopes_outside_the_form(): void
    {
        $business = Business::factory()->create();
        [$quick, $device, $media, $user] = $this->delivery($business);
        foreach ([
            [QuickPlayDisplayMode::Advertising, QuickPlayScope::Devices, 'display_mode'],
            [QuickPlayDisplayMode::Fullscreen, QuickPlayScope::Devices, 'display_mode'],
            [QuickPlayDisplayMode::Business, QuickPlayScope::All, 'scope'],
        ] as [$mode, $scope, $error]) {
            try {
                app(StartQuickPlay::class)->handle($user, $media, $mode, $scope, 10, ['device_ids' => [$device->id]], $business);
                $this->fail('Business dispatch must reject forbidden modes and scopes.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($error, $exception->errors());
            }
        }
        $this->assertDatabaseCount('quick_plays', 1);
        $this->assertDatabaseCount('device_commands', 1);
    }

    public function test_retry_never_follows_a_device_transferred_out_of_the_business(): void
    {
        [$quick, $device, , $user] = $this->delivery(Business::factory()->create());
        $this->markFailed($quick);
        $device->update(['business_id' => Business::factory()->create()->id]);
        $this->actingAs($user)->post('/business/quick-play/'.$quick->id.'/retry')->assertSessionHasErrors('targets');
        $this->assertDatabaseCount('quick_plays', 1);
    }

    public function test_management_requires_permissions_in_both_portals(): void
    {
        [$quick] = $this->delivery();
        $this->markFailed($quick);
        $this->actingAs($this->userWithRole(RoleEnum::Support));
        $this->post('/admin/quick-play/'.$quick->id.'/retry')->assertForbidden();
        $this->delete('/admin/quick-play/'.$quick->id)->assertForbidden();

        [$businessQuick, , , $user] = $this->delivery(Business::factory()->create());
        $this->markFailed($businessQuick);
        $user->syncRoles([]);
        $this->actingAs($user->fresh());
        $this->post('/business/quick-play/'.$businessQuick->id.'/retry')->assertForbidden();
        $this->delete('/business/quick-play/'.$businessQuick->id)->assertForbidden();
        $this->assertDatabaseCount('quick_plays', 2);
        $this->assertSame(2, QuickPlay::query()->count());
    }

    public function test_late_status_cannot_regress_a_failed_attempt_or_change_its_retry(): void
    {
        [$quick, $device, , $user] = $this->delivery();
        $this->markFailed($quick);
        $this->actingAs($user)->post('/admin/quick-play/'.$quick->id.'/retry')->assertSessionHasNoErrors();
        $retry = QuickPlay::query()->latest('id')->firstOrFail();
        $row = $quick->devices()->sole();
        $this->withToken($device->issueToken())->postJson('/api/v1/device/quick-play/status', [
            'quick_play_device_id' => $row->id, 'status' => 'playing',
        ])->assertOk();
        $this->assertSame(QuickPlayDeviceStatus::Failed, $row->fresh()->status);
        $this->assertSame(QuickPlayDeviceStatus::Pending, $retry->devices()->sole()->status);
    }
}
