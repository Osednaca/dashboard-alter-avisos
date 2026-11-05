<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_operator_can_view_devices_but_not_manage_users(): void
    {
        $operator = $this->userWithRole(RoleEnum::Operator);

        $this->actingAs($operator)->get('/admin/devices')->assertOk();
        $this->actingAs($operator)->get('/admin/users')->assertForbidden();
        $this->actingAs($operator)->get('/admin/settings')->assertForbidden();
    }

    public function test_campaign_manager_can_create_campaigns_but_not_manage_users(): void
    {
        $manager = $this->userWithRole(RoleEnum::CampaignManager);

        $this->actingAs($manager)->get('/admin/campaigns/create')->assertOk();
        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    }

    public function test_support_can_view_devices_but_cannot_issue_commands(): void
    {
        $support = $this->userWithRole(RoleEnum::Support);

        $this->actingAs($support)->get('/admin/devices')->assertOk();
        $this->assertFalse($support->hasPermission('devices.commands'));
    }

    public function test_user_permission_names_are_derived_from_roles(): void
    {
        $admin = $this->superAdmin();

        $this->assertContains('businesses.view', $admin->permissionNames());
        $this->assertTrue($admin->hasRole(RoleEnum::SuperAdmin));
    }

    public function test_super_admin_bypasses_gate_checks(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/audit')->assertOk();
    }
}
