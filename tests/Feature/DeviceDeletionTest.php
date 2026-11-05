<?php

namespace Tests\Feature;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Models\DevicePlaybackState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DeviceDeletionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_admin_deletion_cleans_device_rows_and_preserves_other_screens_and_shared_content(): void
    {
        $admin = $this->superAdmin();
        $device = Device::factory()->online()->create();
        $other = Device::factory()->online()->create(['business_id' => $device->business_id]);
        $media = MediaAsset::factory()->image()->create();
        $quick = app(StartQuickPlay::class)->handle($admin, $media, QuickPlayDisplayMode::Fullscreen,
            QuickPlayScope::Devices, 12, ['device_ids' => [$device->id, $other->id]]);
        $device->heartbeats()->create(['recorded_at' => now()]);
        $device->manifests()->create(['version' => 'deletion-test', 'payload' => [], 'generated_at' => now()]);
        $device->playbackEvents()->create(['media_asset_id' => $media->id, 'started_at' => now()]);
        DevicePlaybackState::query()->create([
            'device_id' => $device->id, 'business_id' => $device->business_id,
            'session_id' => (string) Str::uuid(), 'sequence' => 1, 'payload' => [], 'received_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/admin/devices/{$device->id}")
            ->assertRedirect('/admin/devices')->assertSessionHas('success', 'Pantalla eliminada.');

        foreach (['devices' => 'id', 'device_commands' => 'device_id', 'device_heartbeats' => 'device_id',
            'device_manifests' => 'device_id', 'playback_events' => 'device_id',
            'device_playback_states' => 'device_id', 'quick_play_devices' => 'device_id'] as $table => $column) {
            $this->assertDatabaseMissing($table, [$column => $device->id]);
        }
        $this->assertDatabaseHas('devices', ['id' => $other->id]);
        $this->assertDatabaseHas('device_commands', ['device_id' => $other->id]);
        $this->assertDatabaseHas('quick_play_devices', ['device_id' => $other->id, 'quick_play_id' => $quick->id]);
        $this->assertDatabaseHas('businesses', ['id' => $device->business_id]);
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
        $this->assertDatabaseHas('quick_plays', ['id' => $quick->id]);
        $this->get("/admin/devices/{$device->id}")->assertNotFound();
        $this->delete("/admin/devices/{$device->id}")->assertNotFound();
    }

    public function test_deleted_device_token_and_old_activation_cannot_restore_access(): void
    {
        $device = Device::factory()->online()->create();
        $token = $device->issueToken();
        $activation = DeviceActivation::query()->create([
            'code' => 'ABC234', 'device_uuid' => $device->uuid, 'device_id' => $device->id,
            'business_id' => $device->business_id, 'status' => 'claimed', 'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->superAdmin())->delete("/admin/devices/{$device->id}")->assertRedirect();
        $this->assertDatabaseHas('device_activations', ['id' => $activation->id, 'device_id' => null, 'status' => 'revoked']);

        $this->withToken($token)->getJson('/api/v1/device/manifest')->assertUnauthorized();
        $this->postJson('/api/v1/device/activation/confirm', ['code' => $activation->code, 'device_uuid' => $device->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseMissing('devices', ['uuid' => $device->uuid]);

        $new = $this->postJson('/api/v1/device/activation/request', ['device_uuid' => $device->uuid])->assertOk();
        $this->assertNotSame($activation->code, $new->json('activation_code'));
        $this->assertDatabaseHas('device_activations', [
            'code' => $new->json('activation_code'), 'business_id' => null, 'status' => 'pending',
        ]);
    }

    public function test_read_only_staff_and_business_user_cannot_delete_a_screen(): void
    {
        $device = Device::factory()->online()->create();
        $token = $device->issueToken();

        $this->actingAs($this->userWithRole(RoleEnum::Support))->delete("/admin/devices/{$device->id}")->assertForbidden();
        $this->actingAs($this->businessUser($device->business))->delete("/admin/devices/{$device->id}")->assertForbidden();

        $this->assertDatabaseHas('devices', ['id' => $device->id, 'device_token_hash' => hash('sha256', $token)]);
    }

    public function test_guest_cannot_delete_a_screen(): void
    {
        $device = Device::factory()->create();
        $this->delete("/admin/devices/{$device->id}")->assertRedirect('/login');
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }
}
