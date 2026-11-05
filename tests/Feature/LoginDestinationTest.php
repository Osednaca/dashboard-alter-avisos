<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LoginDestinationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_switching_from_admin_to_business_discards_admin_destination_and_context(): void
    {
        $admin = $this->superAdmin(['password' => 'password']);
        $business = Business::factory()->create();
        $user = $this->businessUser($business, ['password' => 'password']);

        $this->login($admin)->assertRedirect(route('dashboard'));
        $this->get('/admin/dashboard')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('page', fn ($page) => ($page['encryptHistory'] ?? false) === true);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/admin/campaigns')->assertRedirect(route('login'));

        $this->withSession(['business_id' => 987654]);
        $this->login($user)
            ->assertRedirect(route('business.dashboard'))
            ->assertSessionMissing('url.intended')
            ->assertSessionMissing('business_id');

        $businessPage = $this->get('/business/dashboard')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('page', fn ($page) => ($page['clearHistory'] ?? false) === true
                && ($page['encryptHistory'] ?? false) === true);
        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $businessPage->viewData('page')['version'] ?? '',
        ])->get('/business/dashboard')->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->flushHeaders();
        $this->get('/admin/campaigns')->assertForbidden();
    }

    public function test_switching_from_business_to_staff_discards_a_business_destination(): void
    {
        $user = $this->businessUser(Business::factory()->create(), ['password' => 'password']);
        $admin = $this->superAdmin(['password' => 'password']);

        $this->login($user)->assertRedirect(route('business.dashboard'));
        $this->post('/logout');
        $this->get('/business/schedule')->assertRedirect(route('login'));

        $this->login($admin)->assertRedirect(route('dashboard'));
    }

    public function test_staff_intended_route_checks_its_permissions(): void
    {
        $operator = $this->userWithRole(RoleEnum::Operator, ['password' => 'password']);

        $this->withSession(['url.intended' => route('campaigns.index')]);
        $this->login($operator)->assertRedirect(route('dashboard'));
        $this->get('/admin/campaigns')->assertForbidden();
        $this->post('/logout');

        $this->withSession(['url.intended' => '/admin/devices?search=Pantalla']);
        $this->login($operator)->assertRedirect('/admin/devices?search=Pantalla');
        $this->get('/admin/devices?search=Pantalla')->assertOk();
    }

    public function test_business_intended_navigation_is_retained_for_an_assigned_user(): void
    {
        $user = $this->businessUser(Business::factory()->create(), ['password' => 'password']);

        $this->withSession(['url.intended' => route('business.library.index')]);
        $this->login($user)->assertRedirect('/business/library');
        $this->get('/business/library')->assertOk();
    }

    public function test_logout_clears_inertia_history_on_the_next_login_page(): void
    {
        $this->actingAs($this->superAdmin())->post('/logout');

        $this->get('/login')->assertOk()
            ->assertViewHas('page', fn ($page) => ($page['clearHistory'] ?? false) === true
                && ! ($page['encryptHistory'] ?? false));
        $this->get('/login')->assertOk()
            ->assertViewHas('page', fn ($page) => ! ($page['clearHistory'] ?? false));
    }

    #[DataProvider('invalidDestinations')]
    public function test_untrusted_or_unprovable_intended_destinations_fall_back(string $intended): void
    {
        $user = $this->superAdmin(['password' => 'password']);

        $this->withSession(['url.intended' => $intended]);
        $this->login($user)
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('url.intended');
    }

    public static function invalidDestinations(): array
    {
        return [
            'external origin' => ['https://evil.example/admin/devices'],
            'protocol relative' => ['//evil.example/admin/devices'],
            'different port' => ['http://localhost:8080/admin/devices'],
            'different scheme' => ['https://localhost/admin/devices'],
            'userinfo' => ['http://guest@localhost/admin/devices'],
            'backslash' => ['/\\evil.example/admin/devices'],
            'unknown route' => ['/admin/unknown'],
            'model policy required' => ['/admin/devices/123'],
            'guest route' => ['/login'],
            'non-get route' => ['/logout'],
        ];
    }

    private function login(User $user): TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }
}
