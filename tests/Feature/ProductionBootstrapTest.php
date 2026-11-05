<?php

namespace Tests\Feature;

use App\Domain\Media\Models\Layout;
use App\Domain\Operations\Models\SystemSetting;
use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\BusinessDemoSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeding_is_repeatable_without_demo_data_or_password_resets(): void
    {
        $this->app['env'] = 'production';
        $user = User::factory()->create(['email' => 'admin@signagetv.co', 'status' => 'suspended']);
        $hash = $user->password;

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $layout = Layout::query()->where('is_default', true)->firstOrFail();
        $this->assertSame('advertising', $layout->configuration['audio_mode']);
        $layout->update(['business_percentage' => 65, 'advertising_percentage' => 35, 'configuration' => ['audio_mode' => 'none']]);
        SystemSetting::query()->where('key', 'network.name')->update(['value' => json_encode(['data' => 'Mi red'])]);
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'suspended']);
        $this->assertDatabaseCount('layouts', 4);
        $this->assertSame('none', $layout->fresh()->configuration['audio_mode']);
        $this->assertDatabaseHas('layouts', ['id' => $layout->id, 'business_percentage' => 65]);
        $this->assertSame(['data' => 'Mi red'], SystemSetting::query()->where('key', 'network.name')->firstOrFail()->value);
        $this->assertDatabaseCount('system_settings', 6);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('devices', 0);
        $this->assertDatabaseCount('advertisers', 0);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
    }

    public function test_demo_seeders_fail_before_writing_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach ([DemoDataSeeder::class, DemoUserSeeder::class, BusinessDemoSeeder::class] as $class) {
            try {
                app($class)->run();
                $this->fail('Demo seeding must be blocked in production.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('local/testing', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('layouts', 0);
    }

    public function test_admin_command_creates_an_active_super_admin_with_a_hashed_password(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('signage:admin')
            ->expectsQuestion('Nombre', 'Mi administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Test-password-2026!')
            ->expectsQuestion('Repite la contraseña', 'Test-password-2026!')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleEnum::SuperAdmin));
        $this->assertTrue(Hash::check('Test-password-2026!', $user->password));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_command_does_not_overwrite_an_existing_user(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $hash = $user->password;

        $this->artisan('signage:admin')
            ->expectsQuestion('Nombre', 'Nuevo nombre')
            ->expectsQuestion('Correo electrónico', $user->email)
            ->assertFailed();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertFalse($user->fresh()->hasRole(RoleEnum::SuperAdmin));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_command_rejects_mismatched_passwords(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('signage:admin')
            ->expectsQuestion('Nombre', 'Administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Test-password-2026!')
            ->expectsQuestion('Repite la contraseña', 'Different-password!')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_command_rejects_short_passwords(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('signage:admin')
            ->expectsQuestion('Nombre', 'Administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'short')
            ->expectsQuestion('Repite la contraseña', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
