<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_assets_use_https_behind_a_trusted_proxy(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.11.0.9',
        ])->withHeaders([
            'Host' => 'signage.example.com',
            'X-Forwarded-Host' => 'signage.example.com',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
        ])->get('/login');

        $response->assertOk();
        $response->assertSee('https://signage.example.com/build/assets/', false);
        $response->assertDontSee('http://signage.example.com/build/assets/', false);
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = $this->superAdmin(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_retired_business_management_permission_does_not_grant_dashboard_access(): void
    {
        $user = $this->businessUser(Business::factory()->create(), ['password' => 'password']);
        $user->roles()->firstOrFail()->permissions()->attach(Permission::query()->where('name', 'businesses.view')->value('id'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_users_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'status' => UserStatus::Suspended,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
