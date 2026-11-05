<?php

namespace Tests\Feature;

use App\Domain\Devices\Actions\BuildDeviceManifest;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/device/admin/settings';

    private function device(): Device
    {
        $layout = Layout::query()->create([
            'name' => 'Compartido', 'orientation' => 'landscape', 'business_percentage' => 70,
            'advertising_percentage' => 30, 'is_default' => true,
            'configuration' => ['business_area' => 'right', 'advertising_area' => 'left', 'audio_mode' => 'none'],
        ]);
        $device = Device::factory()->online()->create(['current_layout_id' => $layout->id]);
        $device->forceFill(['admin_pin_hash' => Hash::make('012345')])->save();

        return $device;
    }

    public function test_tv_saves_layout_and_audio_without_changing_another_tv_or_the_shared_layout(): void
    {
        $device = $this->device();
        $originalId = $device->current_layout_id;
        $other = Device::factory()->create(['current_layout_id' => $originalId]);
        $settings = ['split' => 'top_bottom', 'business_percentage' => 60, 'orientation' => 'portrait', 'audio_mode' => 'advertising'];
        $response = $this->withToken($device->issueToken())->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => $settings])
            ->assertOk()->assertJsonPath('manifest.payload.device.uuid', $device->uuid)
            ->assertJsonPath('manifest.payload.layout.business_percentage', 60)
            ->assertJsonPath('manifest.payload.layout.advertising_percentage', 40)
            ->assertJsonPath('manifest.payload.layout.orientation', 'portrait')
            ->assertJsonPath('manifest.payload.layout.configuration.split', 'top_bottom')
            ->assertJsonPath('manifest.payload.layout.configuration.business_area', 'bottom')
            ->assertJsonPath('manifest.payload.layout.configuration.audio_mode', 'advertising');

        $this->assertNotSame($originalId, $device->fresh()->current_layout_id);
        $this->assertSame($originalId, $other->fresh()->current_layout_id);
        $this->assertSame(70, Layout::findOrFail($originalId)->business_percentage);
        $this->assertTrue(Layout::findOrFail($originalId)->is_default);
        $this->assertFalse($device->fresh()->currentLayout->is_default);
        $this->getJson('/api/v1/device/manifest')->assertOk()->assertJsonPath('manifest.version', $response->json('manifest.version'));
        $audit = AuditLog::query()->where('action', 'device.settings.updated')->sole();
        $this->assertNull($audit->user_id);
        $this->assertSame($device->id, $audit->entity_id);
        $this->assertStringNotContainsString('012345', $audit->toJson());
        $this->assertStringNotContainsString('admin_pin_hash', $response->getContent());
    }

    public function test_repeated_changes_in_one_second_preserve_other_settings_and_generate_increasing_versions(): void
    {
        $this->freezeTime();
        $device = $this->device();
        $this->withToken($device->issueToken());
        $a = $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => ['split' => 'top_bottom']])->assertOk();
        $b = $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => ['business_percentage' => 75]])
            ->assertOk()->assertJsonPath('manifest.payload.layout.configuration.split', 'top_bottom');
        $this->assertGreaterThan((int) $a->json('manifest.version'), (int) $b->json('manifest.version'));
        $c = app(BuildDeviceManifest::class)->handle($device->fresh());
        $this->assertGreaterThan((int) $b->json('manifest.version'), (int) $c->version);
        $this->assertSame(75, $c->payload['layout']['business_percentage']);
        $this->assertSame('top_bottom', $c->payload['layout']['configuration']['split']);
    }

    public function test_invalid_or_missing_authorization_cannot_mutate_settings(): void
    {
        $device = $this->device();
        $body = ['pin' => '012345', 'settings' => ['business_percentage' => 50]];
        $this->patchJson(self::ENDPOINT, $body)->assertUnauthorized();
        $this->withToken('invalid-token')->patchJson(self::ENDPOINT, $body)->assertUnauthorized();
        $this->withToken($device->issueToken());
        $this->patchJson(self::ENDPOINT, [...$body, 'device_id' => $device->id + 1])->assertUnprocessable();
        $device->forceFill(['status' => DeviceStatus::Disabled])->save();
        $this->patchJson(self::ENDPOINT, $body)->assertForbidden();
        $device->revokeToken();
        $this->patchJson(self::ENDPOINT, $body)->assertUnauthorized();
        $this->assertSame(70, $device->fresh()->currentLayout->business_percentage);
        $this->assertDatabaseCount('layouts', 1);
        $this->assertDatabaseCount('device_manifests', 0);
    }

    public function test_settings_validate_supported_fields_and_save_without_a_configured_pin(): void
    {
        $device = $this->device();
        $this->withToken($device->issueToken());
        foreach ([[], ['business_percentage' => 0], ['business_percentage' => 100], ['orientation' => 'invalid'], ['split' => null], ['audio_mode' => 'both'], ['rotation' => 45], ['rotation' => 360], ['transition' => 'invalid'], ['current_layout_id' => 1]] as $settings) {
            $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => $settings])->assertUnprocessable();
        }
        $device->forceFill(['admin_pin_hash' => null])->save();
        $this->assertDatabaseCount('layouts', 1);
        $this->patchJson(self::ENDPOINT, ['settings' => ['orientation' => 'portrait']])->assertOk()
            ->assertJsonPath('manifest.payload.layout.orientation', 'portrait');
    }

    public function test_rotation_cycles_and_transition_survive_subsequent_settings_and_manifest_builds(): void
    {
        $device = $this->device();
        $this->withToken($device->issueToken());
        foreach ([90, 180, 270, 0] as $rotation) {
            $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => ['rotation' => $rotation, 'transition' => 'soft_zoom']])
                ->assertOk()->assertJsonPath('manifest.payload.layout.configuration.rotation', $rotation)
                ->assertJsonPath('manifest.payload.layout.orientation', $rotation % 180 === 0 ? 'landscape' : 'portrait');
        }
        $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => ['split' => 'top_bottom']])->assertOk()
            ->assertJsonPath('manifest.payload.layout.configuration.rotation', 0)
            ->assertJsonPath('manifest.payload.layout.configuration.transition', 'soft_zoom');
        $rebuilt = app(BuildDeviceManifest::class)->handle($device->fresh());
        $this->assertSame(0, $rebuilt->payload['layout']['configuration']['rotation']);
        $this->assertSame('soft_zoom', $rebuilt->payload['layout']['configuration']['transition']);
        $this->patchJson(self::ENDPOINT, ['pin' => '012345', 'settings' => ['orientation' => 'portrait', 'transition' => 'playlist']])->assertOk()
            ->assertJsonPath('manifest.payload.layout.configuration.rotation', 90)
            ->assertJsonPath('manifest.payload.layout.configuration.transition', 'playlist');
    }

    public function test_late_acknowledgement_preserves_new_changes_and_cannot_roll_back_the_tv(): void
    {
        $device = $this->device();
        $this->withToken($device->issueToken());
        $first = app(BuildDeviceManifest::class)->handle($device);
        $second = app(BuildDeviceManifest::class)->handle($device->fresh());
        $this->postJson('/api/v1/device/sync/acknowledge', ['version' => $first->version, 'success' => true])->assertOk();
        $this->getJson('/api/v1/device/sync')->assertOk()->assertJsonPath('pending_manifest_version', $second->version);
        $this->postJson('/api/v1/device/sync/acknowledge', ['version' => $second->version, 'success' => true])->assertOk();
        $this->postJson('/api/v1/device/sync/acknowledge', ['version' => $first->version, 'success' => true])->assertOk();
        $this->getJson('/api/v1/device/sync')->assertOk()->assertJsonPath('current_manifest_version', $second->version)->assertJsonPath('pending_manifest_version', null);
        $this->getJson('/api/v1/device/manifest')->assertOk()->assertJsonPath('manifest.version', $second->version);
    }

    public function test_legacy_pin_lockout_does_not_block_settings_for_new_players(): void
    {
        $device = $this->device();
        $this->withToken($device->issueToken());
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/device/admin/verify-pin', ['pin' => '654321'])->assertForbidden();
        }
        $this->postJson('/api/v1/device/admin/verify-pin', ['pin' => '012345'])->assertStatus(429);
        $this->patchJson(self::ENDPOINT, ['settings' => ['split' => 'top_bottom']])->assertOk();
    }
}
