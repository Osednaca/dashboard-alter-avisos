<?php

namespace Tests\Feature;

use App\Domain\Devices\Actions\AssignActivation;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\Layout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_can_request_an_activation_code(): void
    {
        $response = $this->postJson('/api/v1/device/activation/request', [
            'device_uuid' => '11111111-1111-4111-8111-111111111111',
            'app_version' => '1.6.2',
        ]);

        $response->assertOk()->assertJsonStructure(['activation_code', 'status', 'expires_at']);

        $this->assertDatabaseHas('device_activations', [
            'device_uuid' => '11111111-1111-4111-8111-111111111111',
            'status' => 'pending',
        ]);

        $this->assertSame(6, strlen($response->json('activation_code')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('expires_at'));
    }

    public function test_existing_activation_returns_expiry_in_utc_z_format_without_changing_its_instant(): void
    {
        $this->travelTo(Carbon::parse('2026-09-17T19:52:02Z'));

        DeviceActivation::query()->create([
            'code' => 'ABC234',
            'device_uuid' => '66666666-6666-4666-8666-666666666666',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/device/activation/request', [
            'device_uuid' => '66666666-6666-4666-8666-666666666666',
        ])->assertOk()
            ->assertJsonPath('activation_code', 'ABC234')
            ->assertJsonPath('expires_at', '2026-09-17T20:52:02Z');

        $activation = DeviceActivation::query()->sole();
        $this->assertSame('2026-09-17T20:52:02+00:00', $activation->expires_at->toIso8601String());
    }

    public function test_requesting_twice_returns_the_same_pending_code(): void
    {
        $payload = ['device_uuid' => '22222222-2222-4222-8222-222222222222'];

        $first = $this->postJson('/api/v1/device/activation/request', $payload)->json('activation_code');
        $second = $this->postJson('/api/v1/device/activation/request', $payload)->json('activation_code');

        $this->assertSame($first, $second);
    }

    public function test_a_device_can_confirm_activation_and_receive_a_token(): void
    {
        $location = Location::factory()->create();

        $activation = DeviceActivation::query()->create([
            'code' => 'ABC123',
            'device_uuid' => '33333333-3333-4333-8333-333333333333',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        app(AssignActivation::class)->handle($activation, $location->business_id, $location->id, 'Pantalla Test');

        $response = $this->postJson('/api/v1/device/activation/confirm', [
            'code' => 'abc123',
            'device_uuid' => '33333333-3333-4333-8333-333333333333',
            'app_version' => '1.6.2',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'token_type', 'device']);

        $this->assertDatabaseHas('devices', [
            'name' => 'Pantalla Test',
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);

        $this->assertDatabaseHas('device_activations', ['id' => $activation->id, 'status' => 'claimed']);
    }

    #[DataProvider('businessAreas')]
    public function test_first_manifest_is_private_portrait_and_preserves_shared_defaults(string $businessArea, string $expectedArea): void
    {
        $default = Layout::query()->create([
            'name' => 'Compartido', 'orientation' => 'landscape', 'business_percentage' => 60,
            'advertising_percentage' => 40, 'is_default' => true,
            'configuration' => [
                'business_area' => $businessArea, 'advertising_area' => 'right', 'split' => 'left_right',
                'rotation' => 0, 'audio_mode' => 'advertising', 'transition' => 'soft_zoom',
            ],
        ]);
        $original = $default->fresh()->getAttributes();
        $other = Device::factory()->create(['current_layout_id' => $default->id]);
        $activation = $this->assignedActivation();
        $payload = ['code' => $activation->code, 'device_uuid' => $activation->device_uuid];
        $first = $this->postJson('/api/v1/device/activation/confirm', $payload)->assertCreated();
        $device = $activation->fresh()->device;
        $this->withToken($first->json('token'))->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.payload.layout.id', $device->current_layout_id)
            ->assertJsonPath('manifest.payload.layout.orientation', 'portrait')
            ->assertJsonPath('manifest.payload.layout.business_percentage', 60)
            ->assertJsonPath('manifest.payload.layout.advertising_percentage', 40)
            ->assertJsonPath('manifest.payload.layout.configuration.rotation', 90)
            ->assertJsonPath('manifest.payload.layout.configuration.split', 'top_bottom')
            ->assertJsonPath('manifest.payload.layout.configuration.business_area', $expectedArea)
            ->assertJsonPath('manifest.payload.layout.configuration.advertising_area', $expectedArea === 'top' ? 'bottom' : 'top')
            ->assertJsonPath('manifest.payload.layout.configuration.audio_mode', 'advertising')
            ->assertJsonPath('manifest.payload.layout.configuration.transition', 'soft_zoom');

        $this->assertNotSame($default->id, $device->current_layout_id);
        $this->assertFalse($device->currentLayout->is_default);
        $this->assertSame($original, $default->fresh()->getAttributes());
        $this->assertSame($default->id, $other->fresh()->current_layout_id);

        $retry = $this->postJson('/api/v1/device/activation/confirm', $payload)->assertCreated()
            ->assertJsonPath('device.id', $device->id)
            ->assertJsonPath('device.layout_id', $device->current_layout_id);
        $this->withToken($retry->json('token'))->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.payload.layout.configuration.rotation', 90);
        $this->assertDatabaseCount('layouts', 2);
        $this->assertDatabaseCount('devices', 2);
    }

    public static function businessAreas(): array
    {
        return [
            'left becomes top' => ['left', 'top'], 'top stays top' => ['top', 'top'],
            'right becomes bottom' => ['right', 'bottom'], 'bottom stays bottom' => ['bottom', 'bottom'],
        ];
    }

    public function test_activation_without_a_shared_default_returns_a_valid_portrait_manifest(): void
    {
        $activation = $this->assignedActivation();
        $response = $this->postJson('/api/v1/device/activation/confirm', [
            'code' => $activation->code, 'device_uuid' => $activation->device_uuid,
        ])->assertCreated();
        $this->withToken($response->json('token'))->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.payload.layout.orientation', 'portrait')
            ->assertJsonPath('manifest.payload.layout.configuration.rotation', 90)
            ->assertJsonPath('manifest.payload.layout.configuration.split', 'top_bottom')
            ->assertJsonPath('manifest.payload.layout.configuration.audio_mode', 'advertising')
            ->assertJsonPath('manifest.payload.layout.configuration.business_area', 'top')
            ->assertJsonPath('manifest.payload.layout.configuration.advertising_area', 'bottom')
            ->assertJsonPath('manifest.payload.layout.business_percentage', 70)
            ->assertJsonPath('manifest.payload.layout.advertising_percentage', 30);
        $this->assertFalse($activation->fresh()->device->currentLayout->is_default);
        $this->assertDatabaseCount('layouts', 1);
    }

    #[DataProvider('existingRotations')]
    public function test_existing_device_activation_and_retries_preserve_its_explicit_layout(int $rotation): void
    {
        $layout = Layout::query()->create([
            'name' => 'Elegido', 'orientation' => $rotation === 0 ? 'landscape' : 'portrait',
            'business_percentage' => 80, 'advertising_percentage' => 20, 'is_default' => false,
            'configuration' => ['rotation' => $rotation, 'split' => 'left_right', 'audio_mode' => 'none'],
        ]);
        $device = Device::factory()->create(['current_layout_id' => $layout->id]);
        $activation = $this->assignedActivation($device);
        $original = $layout->fresh()->getAttributes();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = $this->postJson('/api/v1/device/activation/confirm', [
                'code' => $activation->code, 'device_uuid' => $device->uuid,
            ])->assertCreated()->assertJsonPath('device.id', $device->id)->assertJsonPath('device.layout_id', $layout->id);
            $this->withToken($response->json('token'))->getJson('/api/v1/device/manifest')->assertOk()
                ->assertJsonPath('manifest.payload.layout.configuration.rotation', $rotation)
                ->assertJsonPath('manifest.payload.layout.configuration.split', 'left_right');
        }
        $this->assertSame($original, $layout->fresh()->getAttributes());
        $this->assertDatabaseCount('layouts', 1);
        $this->assertDatabaseCount('devices', 1);
    }

    public static function existingRotations(): array
    {
        return ['landscape' => [0], 'opposite portrait' => [270]];
    }

    public function test_existing_device_without_a_layout_keeps_the_shared_default_fallback(): void
    {
        $default = Layout::query()->create([
            'name' => 'Compartido', 'orientation' => 'landscape', 'business_percentage' => 70,
            'advertising_percentage' => 30, 'is_default' => true, 'configuration' => ['rotation' => 0],
        ]);
        $device = Device::factory()->create(['current_layout_id' => null]);
        $activation = $this->assignedActivation($device);
        $this->postJson('/api/v1/device/activation/confirm', [
            'code' => $activation->code, 'device_uuid' => $device->uuid,
        ])->assertCreated()->assertJsonPath('device.layout_id', $default->id);
        $this->assertDatabaseCount('layouts', 1);
    }

    private function assignedActivation(?Device $device = null): DeviceActivation
    {
        $location = Location::factory()->create($device ? ['business_id' => $device->business_id] : []);

        return DeviceActivation::query()->create([
            'code' => 'ABC123', 'status' => 'pending', 'expires_at' => now()->addHour(),
            'business_id' => $device?->business_id ?? $location->business_id,
            'location_id' => $device?->location_id ?? $location->id,
            'device_uuid' => $device?->uuid ?? '77777777-7777-4777-8777-777777777777',
            'device_id' => $device?->id,
        ]);
    }

    public function test_activation_fails_when_code_is_not_assigned_to_a_business(): void
    {
        DeviceActivation::query()->create([
            'code' => 'ZZZ999',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/device/activation/confirm', [
            'code' => 'ZZZ999',
            'device_uuid' => '44444444-4444-4444-8444-444444444444',
        ])->assertStatus(422);
    }

    public function test_expired_codes_cannot_be_confirmed(): void
    {
        DeviceActivation::query()->create([
            'code' => 'OLD000',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/device/activation/confirm', [
            'code' => 'OLD000',
            'device_uuid' => '55555555-5555-4555-8555-555555555555',
        ])->assertStatus(422);
    }

    public function test_unassigned_activation_returns_json_without_an_accept_header(): void
    {
        DeviceActivation::query()->create([
            'code' => 'ZZZ999',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->call('POST', '/api/v1/device/activation/confirm', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'code' => 'ZZZ999',
            'device_uuid' => '44444444-4444-4444-8444-444444444444',
        ]))->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors('code');
    }

    public function test_unknown_api_routes_return_json_without_an_accept_header(): void
    {
        $this->get('/api/v1/device/unknown')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
