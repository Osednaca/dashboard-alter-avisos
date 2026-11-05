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

    public function test_legacy_portal_routes_are_unavailable_for_guests_and_staff(): void
    {
        $this->get('/business/dashboard')->assertNotFound();
        $this->actingAs($this->superAdmin())->get('/business/library')->assertNotFound();
    }

    public function test_legacy_business_only_account_cannot_sign_in_to_the_administration_panel(): void
    {
        $user = $this->businessUser(Business::factory()->create(), ['password' => 'password']);

        $this->login($user)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('business_users', ['user_id' => $user->id]);
    }

    public function test_a_removed_portal_destination_falls_back_to_the_admin_dashboard(): void
    {
        $admin = $this->superAdmin(['password' => 'password']);

        $this->withSession(['url.intended' => '/business/schedule']);
        $this->login($admin)->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended');
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
