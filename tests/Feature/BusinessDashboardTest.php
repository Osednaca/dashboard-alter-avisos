<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessDashboardTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_removed_portal_routes_cannot_be_accessed_by_guests_or_either_account_type(): void
    {
        $business = Business::factory()->create();
        $legacyUser = $this->businessUser($business);
        $admin = $this->superAdmin();
        $routes = [
            ['GET', '/business/dashboard'], ['GET', '/business/search?q=test'],
            ['GET', '/business/content'], ['GET', '/business/screens'],
            ['GET', '/business/screens/123'], ['POST', '/business/screens/123/sync'],
            ['GET', '/business/preview'], ['GET', '/business/reports'],
            ['POST', '/business/switch'],
        ];
        foreach ($routes as [$method, $uri]) {
            $this->call($method, $uri)->assertNotFound();
            $this->actingAs($legacyUser)->call($method, $uri)->assertNotFound();
            $this->actingAs($admin)->call($method, $uri)->assertNotFound();
            $this->post('/logout');
        }
    }

    public function test_legacy_user_does_not_gain_access_to_administration_or_global_settings(): void
    {
        $user = $this->businessUser(Business::factory()->create());
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }
}
