<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\GlobalScreenPin;
use App\Domain\Operations\Models\AuditLog;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DeviceAdminPinTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const MANAGEMENT = '/admin/devices/global-pin';

    private const VERIFY = '/api/v1/device/admin/verify-pin';

    private function configure(string $pin = '012345'): void
    {
        $this->actingAs($this->superAdmin())->put(self::MANAGEMENT, [
            'pin' => $pin, 'pin_confirmation' => $pin,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_staff_sets_and_rotates_one_hashed_pin_without_exposing_it(): void
    {
        $device = Device::factory()->create();
        $this->configure();
        $setting = GlobalScreenPin::current();
        $this->assertTrue(Hash::check('012345', $setting->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $setting->toArray());
        $this->assertNull($device->fresh()->admin_pin_hash);
        $this->get(self::MANAGEMENT)->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Devices/GlobalPin')->where('configured', true)->missing('pin_hash')->missing('pin'));
        $this->get("/admin/devices/{$device->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('adminPinConfigured', true)->where('canManagePin', true)->missing('device.admin_pin_hash'));
        $settingsResponse = $this->get('/admin/settings')->assertOk();
        $this->assertStringNotContainsString($setting->pin_hash, $settingsResponse->getContent());
        $audit = AuditLog::query()->where('action', 'screen.global_pin.updated')->sole();
        $this->assertNull($audit->new_values);
        $this->assertNull($audit->old_values);
        $this->assertStringNotContainsString('012345', $audit->toJson());
        $this->configure('654321');
        $this->assertDatabaseCount('global_screen_pins', 1);
        $this->assertFalse(Hash::check('012345', GlobalScreenPin::current()->pin_hash));
        $this->assertTrue(Hash::check('654321', GlobalScreenPin::current()->pin_hash));
    }

    public function test_management_preserves_staff_permission_and_never_flashes_invalid_pins(): void
    {
        $device = Device::factory()->create();
        foreach ([$this->userWithRole(RoleEnum::Support), $this->businessUser(Business::factory()->create())] as $user) {
            $this->actingAs($user)->get(self::MANAGEMENT)->assertForbidden();
            $this->put(self::MANAGEMENT, ['pin' => '123456', 'pin_confirmation' => '123456'])->assertForbidden();
        }
        $this->assertNull(GlobalScreenPin::current());
        $this->actingAs($this->superAdmin());
        foreach (['', '12345', 'abcdef', '１２３４５６', ' 123456 ', '123456'] as $pin) {
            $this->put(self::MANAGEMENT, ['pin' => $pin, 'pin_confirmation' => 'different'])
                ->assertSessionHasErrors('pin')->assertSessionMissing('_old_input.pin')->assertSessionMissing('_old_input.pin_confirmation');
        }
        $this->assertNull(GlobalScreenPin::current());
        $this->post("/admin/devices/{$device->id}/admin-pin", ['pin' => '123456', 'pin_confirmation' => '123456'])->assertNotFound();
        $this->assertNull($device->fresh()->admin_pin_hash);
        $this->actingAs($this->userWithRole(RoleEnum::Operator))->put(self::MANAGEMENT, ['pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('123456', GlobalScreenPin::current()->pin_hash));
    }

    public function test_every_business_uses_global_pin_and_old_device_pins_never_work(): void
    {
        $devices = collect([Business::factory()->create(), Business::factory()->create()])
            ->map(fn ($business) => Device::factory()->online()->create(['business_id' => $business->id]));
        foreach ($devices as $device) {
            $device->forceFill(['admin_pin_hash' => Hash::make('987654')])->save();
            $this->withToken($device->issueToken())->postJson(self::VERIFY, ['pin' => '987654'])->assertStatus(409);
        }
        $this->configure();
        foreach ($devices as $device) {
            $this->withToken($device->issueToken())->postJson(self::VERIFY, ['pin' => '987654'])->assertForbidden();
            $this->postJson(self::VERIFY, ['pin' => '012345'])->assertOk()->assertExactJson(['authorized' => true]);
        }
        $this->configure('654321');
        foreach ($devices as $device) {
            $this->withToken($device->issueToken())->postJson(self::VERIFY, ['pin' => '012345'])->assertForbidden();
            $this->postJson(self::VERIFY, ['pin' => '654321'])->assertOk();
        }
        $newDevice = Device::factory()->online()->create();
        $this->withToken($newDevice->issueToken())->postJson(self::VERIFY, ['pin' => '654321'])->assertOk();
    }

    public function test_only_authenticated_active_devices_can_verify_and_input_remains_strict(): void
    {
        $device = Device::factory()->online()->create();
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertUnauthorized();
        $this->configure();
        $this->withToken('invalid')->postJson(self::VERIFY, ['pin' => '012345'])->assertUnauthorized();
        $this->withToken($device->issueToken());
        foreach (['', '12345', 'abcdef', '１２３４５６', ' 012345 '] as $pin) {
            $this->postJson(self::VERIFY, ['pin' => $pin])->assertUnprocessable();
        }
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertOk();
        $device->forceFill(['status' => DeviceStatus::Disabled])->save();
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertForbidden();
        $device->revokeToken();
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertUnauthorized();
    }

    public function test_per_device_lockout_expires_and_successes_are_not_counted(): void
    {
        $this->configure();
        $device = Device::factory()->online()->create();
        $this->withToken($device->issueToken());
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::VERIFY, ['pin' => '012345'])->assertOk();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::VERIFY, ['pin' => '111111'])->assertForbidden();
        }
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(301)->seconds();
        $this->postJson(self::VERIFY, ['pin' => '012345'])->assertOk();
    }

    public function test_switching_screens_cannot_bypass_the_shared_ip_limit_or_clear_it_with_a_success(): void
    {
        $this->configure();
        for ($deviceNumber = 0; $deviceNumber < 6; $deviceNumber++) {
            $device = Device::factory()->online()->create();
            $this->withToken($device->issueToken())->postJson(self::VERIFY, ['pin' => '012345'])->assertOk();
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->postJson(self::VERIFY, ['pin' => '111111'])->assertForbidden();
            }
        }
        $other = Device::factory()->online()->create();
        $this->withToken($other->issueToken())->postJson(self::VERIFY, ['pin' => '012345'])->assertStatus(429);
        $this->configure('654321');
        $this->withToken($other->issueToken())->postJson(self::VERIFY, ['pin' => '654321'])->assertOk();
    }

    public function test_distributed_devices_and_ips_are_bounded_by_a_broad_global_limit(): void
    {
        $this->configure();
        // TV calls do not carry a dashboard session. Avoid counting all requests
        // against the test's staff identity in the outer device-api throttle.
        $this->app['auth']->guard('web')->logout();
        for ($number = 0; $number < 60; $number++) {
            $device = Device::factory()->online()->create();
            $this->withHeaders(['REMOTE_ADDR' => '192.0.2.'.($number + 1)])->withToken($device->issueToken());
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $response = $this->postJson(self::VERIFY, ['pin' => '111111']);
                $this->assertSame(403, $response->status(), 'Device '.$number.' attempt '.$attempt);
            }
        }
        $other = Device::factory()->online()->create();
        $this->withHeaders(['REMOTE_ADDR' => '192.0.2.100'])->withToken($other->issueToken())
            ->postJson(self::VERIFY, ['pin' => '012345'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_migration_does_not_promote_legacy_hash_and_can_be_rolled_back_in_isolation(): void
    {
        $device = Device::factory()->create(['admin_pin_hash' => Hash::make('987654')]);
        $migration = require database_path('migrations/2026_10_03_120000_create_global_screen_pins_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('global_screen_pins', 0);
        $this->assertTrue(Hash::check('987654', $device->fresh()->admin_pin_hash));
    }
}
