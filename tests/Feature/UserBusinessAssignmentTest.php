<?php

namespace Tests\Feature;

use App\Domain\Businesses\Models\Business;
use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UserBusinessAssignmentTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_legacy_business_role_cannot_be_assigned_to_new_users(): void
    {
        $this->actingAs($this->superAdmin())->post('/admin/users', [
            'name' => 'Usuario anterior', 'email' => 'legacy@example.com', 'status' => 'active',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'roles' => [RoleEnum::BusinessUser->value],
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'legacy@example.com']);
    }

    public function test_an_existing_account_can_be_given_a_staff_role_without_deleting_its_history(): void
    {
        $admin = $this->superAdmin();
        $business = Business::factory()->create();
        $user = $this->businessUser($business);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'status' => 'active',
            'password' => '', 'password_confirmation' => '', 'roles' => [RoleEnum::Operator->value],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole(RoleEnum::Operator));
        $this->assertDatabaseHas('business_users', ['user_id' => $user->id, 'business_id' => $business->id]);
    }
}
