<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_staff_user(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'firstName' => 'Front', 'lastName' => 'Desk', 'email' => 'desk@example.com',
            'role' => 'front_desk', 'status' => 'active', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'desk@example.com', 'role' => 'front_desk']);
    }

    public function test_non_admin_cannot_manage_staff_users(): void
    {
        $staff = User::factory()->create(['role' => 'front_desk']);
        $this->actingAs($staff)->get(route('dashboard.users.index'))->assertForbidden();
    }

    public function test_super_admin_cannot_demote_or_suspend_self(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('dashboard.users.update', $admin), [
            'firstName' => $admin->firstName, 'lastName' => $admin->lastName, 'email' => $admin->email,
            'role' => 'manager', 'status' => 'suspended', 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasErrors('role');
        $this->assertSame('super_admin', $admin->fresh()->role);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_custom_role_permissions_are_enforced_and_assignment_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['hotel_id' => $admin->hotel_id, 'role' => 'front_desk']);
        $this->actingAs($admin)->post(route('dashboard.roles.store'), ['name' => 'Security Officer', 'base_role' => 'front_desk', 'description' => 'Audit access', 'permissions' => ['dashboard', 'security']])->assertSessionHasNoErrors();
        $role = \App\Models\StaffRole::firstOrFail();
        $this->actingAs($admin)->patch(route('dashboard.users.role', $staff), ['staff_role_id' => $role->id, 'change_reason' => 'Assigned to the security desk'])->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->hasPermission('security'));
        $this->assertFalse($staff->fresh()->hasPermission('guests'));
        $this->actingAs($staff->fresh())->get(route('dashboard.security.index'))->assertOk();
        $this->actingAs($staff->fresh())->get(route('dashboard.guests.index'))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['action' => 'staff_role_assigned']);
    }
}
