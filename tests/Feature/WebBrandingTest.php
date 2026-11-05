<?php

namespace Tests\Feature;

use App\Domain\Operations\Models\SystemSetting;
use Database\Seeders\SystemDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_logo_is_the_exact_supplied_transparent_png(): void
    {
        $logo = public_path('brand/alter-logo-20261003.png');

        $this->assertSame('f80febb0b718a6ebb15c3b14697b79adb9ece633afeb0b96728ef322df9bd07d', hash_file('sha256', $logo));
        $metadata = getimagesize($logo);
        $this->assertSame([1064, 1064, 'image/png'], [$metadata[0], $metadata[1], $metadata['mime']]);
    }

    public function test_login_uses_alter_without_changing_technical_application_identity(): void
    {
        config(['app.name' => 'Signage TV', 'session.cookie' => 'signage-tv-session', 'cache.prefix' => 'signage-tv-cache', 'database.redis.options.prefix' => 'signage-tv-redis']);

        $this->get('/login')->assertOk()
            ->assertSee('<title inertia>Alter</title>', false)
            ->assertSee('rel="icon" type="image/png" href="/brand/alter-logo-20261003.png"', false)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')
                ->where('app.name', 'Alter')->where('app.logo_url', '/brand/alter-logo-20261003.png'));

        $this->assertSame('Signage TV', config('app.name'));
        $this->assertSame('signage-tv-session', config('session.cookie'));
        $this->assertSame('signage-tv-cache', config('cache.prefix'));
        $this->assertSame('signage-tv-redis', config('database.redis.options.prefix'));
    }

    public function test_old_configuration_cache_without_branding_still_has_a_visible_brand(): void
    {
        config(['branding' => []]);
        $this->get('/login')->assertOk()->assertSee('<title inertia>Alter</title>', false)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')
                ->where('app.name', 'Alter')->where('app.logo_url', '/brand/alter-logo-20261003.png'));
    }

    public function test_migration_updates_only_legacy_name_preserving_other_fields_and_is_idempotent(): void
    {
        $setting = SystemSetting::query()->create([
            'key' => 'network.name', 'value' => ['data' => 'Red Signage TV Colombia', 'custom' => ['keep' => true]],
            'group' => 'my-group', 'label' => 'My label',
        ]);
        SystemSetting::put('network.default_timezone', 'America/Bogota');
        $migration = require database_path('migrations/2026_09_27_000000_update_legacy_network_brand.php');
        $migration->up();
        $migration->up();

        $this->assertSame(['data' => 'Red Alter Colombia', 'custom' => ['keep' => true]], $setting->fresh()->value);
        $this->assertSame('my-group', $setting->fresh()->group);
        $this->assertSame('My label', $setting->fresh()->label);
        $this->assertSame('America/Bogota', SystemSetting::get('network.default_timezone'));
    }

    public function test_migration_preserves_custom_network_names_and_does_not_create_missing_settings(): void
    {
        $migration = require database_path('migrations/2026_09_27_000000_update_legacy_network_brand.php');
        $migration->up();
        $this->assertDatabaseCount('system_settings', 0);

        foreach (['Mi red', 'Red Signage TV Colombia personalizada', 'Red Alter Colombia'] as $name) {
            SystemSetting::put('network.name', $name);
            $migration->up();
            $migration->down();
            $this->assertSame($name, SystemSetting::get('network.name'));
        }
    }

    public function test_new_installations_seed_alter_without_overwriting_a_custom_network_name(): void
    {
        $this->seed(SystemDefaultsSeeder::class);
        $this->assertSame('Red Alter Colombia', SystemSetting::get('network.name'));
        SystemSetting::put('network.name', 'Mi red');
        $this->seed(SystemDefaultsSeeder::class);
        $this->assertSame('Mi red', SystemSetting::get('network.name'));
    }
}
