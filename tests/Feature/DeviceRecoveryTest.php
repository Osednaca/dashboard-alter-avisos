<?php

namespace Tests\Feature;

use App\Domain\Devices\Actions\RecoverDeviceActivation;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Models\AuditLog;
use App\Http\Presenters\EntityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DeviceRecoveryTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const RECOVER = '/api/v1/device/activation/recovery';

    private const ENROLL = self::RECOVER.'/enroll';

    private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function device(): Device
    {
        $device = Device::factory()->online()->create();
        $device->business->update(['status' => 'active']);

        return $device;
    }

    private function enroll(Device $device): string
    {
        $token = $device->issueToken();
        $this->withToken($token)->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertNoContent();

        return $token;
    }

    public function test_enrollment_requires_a_valid_bearer_and_never_accepts_public_uuid_as_proof(): void
    {
        $device = $this->device();
        $this->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertUnauthorized();
        $this->withToken('invalid')->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertUnauthorized();
        $this->postJson(self::RECOVER, ['device_uuid' => $device->uuid])->assertUnprocessable();
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound();
        $this->assertNull($device->fresh()->recovery_key_hash);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_reinstall_recovers_original_assignment_and_rotates_bearer_without_changing_playback(): void
    {
        $device = $this->device();
        $layout = Layout::query()->create(['name' => 'Elegido', 'orientation' => 'portrait', 'business_percentage' => 60,
            'advertising_percentage' => 40, 'configuration' => ['rotation' => 270, 'audio_mode' => 'none']]);
        $playlist = $device->business->playlists()->create(['name' => 'Programación', 'type' => 'business', 'status' => 'active']);
        $device->update(['current_layout_id' => $layout->id, 'current_playlist_id' => $playlist->id]);
        $old = $this->enroll($device);
        $original = $device->fresh()->only(['uuid', 'business_id', 'location_id', 'name', 'current_layout_id', 'current_playlist_id', 'current_manifest_version']);
        $response = $this->postJson(self::RECOVER, ['recovery_key' => self::KEY, 'app_version' => '0.1.21'])
            ->assertOk()->assertJsonPath('device.uuid', $device->uuid)->assertJsonPath('device.layout_id', $layout->id)
            ->assertJsonPath('token_type', 'Bearer');
        $this->assertSame($original, $device->fresh()->only(array_keys($original)));
        $this->assertSame('0.1.21', $device->fresh()->app_version);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('layouts', 1);
        $this->withToken($old)->getJson('/api/v1/device/manifest')->assertUnauthorized();
        $this->withToken($response->json('token'))->getJson('/api/v1/device/manifest')->assertOk()
            ->assertJsonPath('manifest.payload.layout.configuration.audio_mode', 'none');
        $this->assertStringNotContainsString(self::KEY, $response->getContent());
    }

    public function test_enrollment_is_idempotent_but_cannot_replace_or_steal_a_capability(): void
    {
        $device = $this->device();
        $token = $this->enroll($device);
        $this->withToken($token)->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertNoContent();
        $this->postJson(self::ENROLL, ['recovery_key' => str_repeat('b', 64)])->assertConflict();
        $other = $this->device();
        $this->withToken($other->issueToken())->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertConflict();
        $this->assertSame(hash('sha256', self::KEY), $device->fresh()->recovery_key_hash);
        $this->assertNull($other->fresh()->recovery_key_hash);
    }

    public function test_expired_bearer_cannot_enroll_but_previously_enrolled_expired_token_can_recover(): void
    {
        $device = $this->device();
        $token = $this->enroll($device);
        $device->forceFill(['token_expires_at' => now()->subMinute()])->save();
        $this->withToken($token)->postJson(self::ENROLL, ['recovery_key' => self::KEY])->assertUnauthorized();
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertOk();
        $this->assertTrue($device->fresh()->token_expires_at->isFuture());
    }

    #[DataProvider('deniedStates')]
    public function test_ineligible_devices_return_the_same_error_and_never_issue_a_token(string $change): void
    {
        $device = $this->device();
        $this->enroll($device);
        match ($change) {
            'disabled' => $device->update(['status' => 'disabled']),
            'pending' => $device->update(['status' => 'pending_activation']),
            'revoked' => $device->revokeToken(),
            'marked_revoked' => $device->forceFill(['token_revoked_at' => now()])->save(),
            'no_token' => $device->forceFill(['device_token_hash' => null])->save(),
            'deleted' => $device->delete(),
            'business_deleted' => $device->business->delete(),
            default => $device->business->update(['status' => $change]),
        };
        $before = $device->fresh()?->getAttributes();
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound()
            ->assertJsonPath('message', 'Activación no recuperable.');
        $this->assertSame($before, $device->fresh()?->getAttributes());
    }

    public static function deniedStates(): array
    {
        return array_map(fn ($s) => [$s], ['disabled', 'pending', 'revoked', 'marked_revoked', 'no_token', 'deleted', 'business_deleted', 'inactive', 'suspended', 'onboarding']);
    }

    public function test_revocation_and_admin_deletion_permanently_invalidate_recovery(): void
    {
        $device = $this->device();
        $this->enroll($device);
        $this->actingAs($this->superAdmin())->post('/admin/devices/'.$device->id.'/revoke-token')->assertRedirect();
        $this->assertNull($device->fresh()->recovery_key_hash);
        $device->issueToken();
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound();
        $this->enroll($device);
        $this->delete('/admin/devices/'.$device->id)->assertRedirect();
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound();
    }

    public function test_reactivation_clears_previous_tv_recovery_before_assignment_can_be_reused(): void
    {
        $device = $this->device();
        $this->enroll($device);
        DeviceActivation::query()->create(['code' => 'ABC123', 'status' => 'claimed', 'business_id' => $device->business_id,
            'device_id' => $device->id, 'device_uuid' => $device->uuid, 'expires_at' => now()->addHour()]);
        $this->postJson('/api/v1/device/activation/confirm', ['code' => 'ABC123', 'device_uuid' => $device->uuid])->assertCreated();
        $this->assertNull($device->fresh()->recovery_key_hash);
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound();
    }

    public function test_retry_after_lost_response_rotates_again_and_only_latest_bearer_is_valid(): void
    {
        $device = $this->device();
        $this->enroll($device);
        $a = $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertOk()->json('token');
        $b = $this->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertOk()->json('token');
        $this->assertNotSame($a, $b);
        $this->withToken($a)->getJson('/api/v1/device/manifest')->assertUnauthorized();
        $this->withToken($b)->getJson('/api/v1/device/manifest')->assertOk();
    }

    public function test_enrollment_rechecks_bearer_inside_transaction_after_middleware_race(): void
    {
        $device = $this->device();
        $stale = $device->issueToken();
        $device->issueToken();
        try {
            app(RecoverDeviceActivation::class)->enroll($device->id, $stale, self::KEY);
            $this->fail('Stale middleware authorization must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
        $this->assertNull($device->fresh()->recovery_key_hash);
    }

    public function test_stale_model_token_issuance_and_revocation_clear_enrollment(): void
    {
        $device = $this->device();
        $this->enroll($device);
        $device->issueToken();
        $this->assertNull($device->fresh()->recovery_key_hash);
        $this->enroll($device);
        $device->revokeToken();
        $this->assertNull($device->fresh()->recovery_key_hash);
        $this->assertNull($device->fresh()->device_token_hash);
        $device->issueToken();
        $this->assertNull($device->fresh()->token_revoked_at);
    }

    public function test_recovery_is_rate_limited_by_capability_across_ips(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertNotFound();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.6'])->postJson(self::RECOVER, ['recovery_key' => self::KEY])->assertTooManyRequests();
    }

    public function test_recovery_is_rate_limited_by_ip_across_capabilities(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson(self::RECOVER, ['recovery_key' => hash('sha256', (string) $i)])->assertNotFound();
        }
        $this->postJson(self::RECOVER, ['recovery_key' => hash('sha256', '11')])->assertTooManyRequests();
    }

    public function test_malformed_recovery_values_return_validation_errors_without_server_errors(): void
    {
        foreach ([null, 12345, ['secret']] as $value) {
            $this->postJson(self::RECOVER, ['recovery_key' => $value])->assertUnprocessable()
                ->assertJsonValidationErrors('recovery_key');
        }
    }

    public function test_recovery_material_is_hidden_and_validation_does_not_echo_it(): void
    {
        $device = $this->device();
        $this->enroll($device);
        $hash = hash('sha256', self::KEY);
        foreach ([$device->fresh()->toJson(), json_encode(EntityPresenter::device($device->fresh())), AuditLog::all()->toJson()] as $output) {
            $this->assertStringNotContainsString($hash, $output);
            $this->assertStringNotContainsString(self::KEY, $output);
            $this->assertStringNotContainsString('recovery_key', $output);
        }
        $this->assertNull($device->fresh()->metadata);
        $invalid = self::KEY.'SECRET';
        $response = $this->postJson(self::RECOVER, ['recovery_key' => $invalid])->assertUnprocessable();
        $this->assertStringNotContainsString($invalid, $response->getContent());
        $this->postJson(self::RECOVER, ['recovery_key' => self::KEY.' '])->assertUnprocessable();
    }
}
