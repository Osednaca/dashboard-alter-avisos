<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_removed_business_management_routes_cannot_read_or_change_legacy_records(): void
    {
        $business = Business::factory()->create(['name' => 'Registro anterior']);
        $this->actingAs($this->superAdmin());
        foreach ([['GET', '/admin/businesses'], ['POST', '/admin/businesses'],
            ['GET', "/admin/businesses/{$business->id}"], ['PUT', "/admin/businesses/{$business->id}"],
            ['DELETE', "/admin/businesses/{$business->id}"]] as [$method, $uri]) {
            $this->call($method, $uri, ['name' => 'Cambio'])->assertNotFound();
        }
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Registro anterior']);
    }
}
