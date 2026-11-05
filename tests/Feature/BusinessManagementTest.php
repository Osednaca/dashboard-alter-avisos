<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_businesses_index_renders_for_authorized_user(): void
    {
        Business::factory()->count(3)->create();

        $this->actingAs($this->superAdmin())
            ->get('/admin/businesses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Businesses/Index'));
    }

    public function test_a_business_can_be_created(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/businesses', [
                'name' => 'Café Los Cerros',
                'category' => 'cafe',
                'status' => 'onboarding',
                'timezone' => 'America/Bogota',
                'contact_name' => 'Ana Díaz',
                'contact_email' => 'ana@loscerros.co',
                'contact_phone' => '+57 300 111 2222',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('businesses', [
            'name' => 'Café Los Cerros',
            'slug' => 'cafe-los-cerros',
            'category' => 'cafe',
        ]);
    }

    public function test_business_creation_validates_required_fields(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/businesses', [])
            ->assertSessionHasErrors(['name', 'category', 'status', 'timezone']);
    }

    public function test_a_business_can_be_updated_and_audited(): void
    {
        $business = Business::factory()->create(['name' => 'Original']);

        $this->actingAs($this->superAdmin())
            ->put("/admin/businesses/{$business->id}", [
                'name' => 'Renombrado',
                'category' => $business->category->value,
                'status' => 'active',
                'timezone' => 'America/Bogota',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Renombrado']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business.updated', 'entity_id' => $business->id]);
    }

    public function test_a_business_can_be_deleted(): void
    {
        $business = Business::factory()->create();

        $this->actingAs($this->superAdmin())
            ->delete("/admin/businesses/{$business->id}")
            ->assertRedirect(route('businesses.index'));

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
    }
}
