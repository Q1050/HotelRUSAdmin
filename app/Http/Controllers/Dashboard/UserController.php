<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Support\StaffPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function roles(): Response
    {
        return Inertia::render('Dashboard/Users/Roles', ['roles' => StaffRole::withCount('users')->orderBy('name')->get()->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'baseRole' => $role->base_role, 'description' => $role->description, 'permissions' => $role->permissions, 'usersCount' => $role->users_count]), 'users' => User::with('staffRole')->orderBy('name')->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'staffRoleId' => $user->staff_role_id, 'roleName' => $user->staffRole?->name]), 'permissionCatalog' => StaffPermissions::ALL, 'templates' => StaffPermissions::TEMPLATES]);
    }

    public function index(): Response
    {
        return Inertia::render('Dashboard/Users/Users', ['users' => User::with('staffRole')->latest('id')->get()->map(fn (User $user) => [
            'id' => $user->id, 'firstName' => $user->firstName, 'lastName' => $user->lastName,
            'email' => $user->email, 'role' => $user->role, 'status' => $user->status,
            'staffRoleId' => $user->staff_role_id, 'roleName' => $user->staffRole?->name,
            'lastLoginAt' => $user->last_login_at?->toISOString(), 'createdAt' => $user->created_at?->toISOString(),
        ]), 'guests' => Guest::latest('id')->get()->map(fn (Guest $guest) => [
            'id' => $guest->id, 'name' => trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')),
            'email' => $guest->email, 'phone' => $guest->phone, 'idStatus' => $guest->id_status,
            'createdAt' => $guest->created_at?->toISOString(),
        ]), 'roles' => StaffRole::withCount('users')->orderBy('name')->get()->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'baseRole' => $role->base_role, 'description' => $role->description, 'permissions' => $role->permissions, 'usersCount' => $role->users_count]), 'permissionCatalog' => StaffPermissions::ALL, 'templates' => StaffPermissions::TEMPLATES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        if (! empty($validated['staff_role_id'])) {
            $validated['role'] = StaffRole::findOrFail($validated['staff_role_id'])->base_role;
        }
        $user = User::create([...$validated, 'name' => $validated['firstName'].' '.$validated['lastName']]);
        AuditLogger::record($request, 'staff_created', 'accounts', 'sensitive', "Staff account {$user->email} created.", $user, null, ['role' => $user->role]);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        if (! empty($validated['staff_role_id'])) {
            $validated['role'] = StaffRole::findOrFail($validated['staff_role_id'])->base_role;
        }
        if ($request->user()->is($user) && (($validated['status'] ?? 'active') !== 'active' || ($validated['role'] ?? '') !== 'super_admin' || ! empty($validated['staff_role_id']))) {
            return back()->withErrors(['role' => 'You cannot suspend or remove your own Super Admin access.']);
        }
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $before = $user->only(['role', 'status', 'email']);
        $sensitive = $before['role'] !== $validated['role'] || $before['status'] !== $validated['status'];
        $sensitive = $sensitive || $user->staff_role_id !== ($validated['staff_role_id'] ?? null);
        if ($user->role === 'super_admin' && ($validated['role'] !== 'super_admin' || ! empty($validated['staff_role_id']) || $validated['status'] !== 'active')) {
            abort_if(User::where('role', 'super_admin')->whereNull('staff_role_id')->where('status', 'active')->whereKeyNot($user->id)->doesntExist(), 422, 'The property must retain at least one active full-access administrator.');
        }
        abort_if($sensitive && blank($validated['change_reason'] ?? null), 422, 'A reason is required when changing a staff role or status.');
        $user->update([...$validated, 'name' => $validated['firstName'].' '.$validated['lastName']]);
        if ($sensitive) {
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }
        AuditLogger::record($request, 'staff_updated', 'accounts', 'sensitive', "Staff account {$user->email} updated.", $user, $request->string('change_reason')->toString() ?: null, ['before' => $before, 'after' => $user->only(['role', 'status', 'email'])]);

        return back()->with('success', 'Staff account updated.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'firstName' => ['required', 'string', 'max:100'], 'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['super_admin', 'manager', 'front_desk', 'housekeeping', 'maintenance'])],
            'staff_role_id' => ['nullable', 'integer', 'exists:staff_roles,id'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'change_reason' => [$user ? 'nullable' : 'exclude', 'string', 'max:500'],
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);
        $role = StaffRole::create($data);
        AuditLogger::record($request, 'staff_role_created', 'accounts', 'sensitive', "Custom role {$role->name} created.", $role, null, ['permissions' => $role->permissions]);

        return back()->with('success', 'Custom role created.');
    }

    public function updateRole(Request $request, StaffRole $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role);
        $before = $role->only(['name', 'base_role', 'permissions']);
        $role->update($data);
        $role->users()->each(function ($user) use ($role) {
            $user->update(['role' => $role->base_role]);
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
        AuditLogger::record($request, 'staff_role_updated', 'accounts', 'critical', "Custom role {$role->name} permissions changed.", $role, $request->string('change_reason'), ['before' => $before, 'after' => $role->only(['name', 'base_role', 'permissions'])]);

        return back()->with('success', 'Role updated and affected sessions revoked.');
    }

    public function destroyRole(Request $request, StaffRole $role): RedirectResponse
    {
        abort_if($role->users()->exists(), 422, 'Reassign users before deleting this role.');
        $name = $role->name;
        $role->delete();
        AuditLogger::record($request, 'staff_role_deleted', 'accounts', 'critical', "Custom role {$name} deleted.");

        return back()->with('success', 'Role deleted.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['staff_role_id' => ['nullable', 'exists:staff_roles,id'], 'change_reason' => ['required', 'string', 'max:500']]);
        abort_if($request->user()->is($user), 422, 'Use another full administrator to change your own access.');
        $before = $user->staffRole?->name ?? $user->role;
        if ($data['staff_role_id']) {
            $role = StaffRole::findOrFail($data['staff_role_id']);
            $user->update(['staff_role_id' => $role->id, 'role' => $role->base_role]);
        } else {
            $user->update(['staff_role_id' => null]);
        }
        AuditLogger::record($request, 'staff_role_assigned', 'accounts', 'critical', "Access role changed for {$user->email}.", $user, $data['change_reason'], ['before' => $before, 'after' => $user->fresh()->staffRole?->name ?? $user->role]);
        $user->tokens()->delete();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', 'Role assigned and existing sessions revoked.');
    }

    private function validateRole(Request $request, ?StaffRole $role = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('staff_roles')->where('hotel_id', $request->user()->hotel_id)->ignore($role)], 'base_role' => ['required', Rule::in(['manager', 'front_desk', 'housekeeping', 'maintenance'])], 'description' => ['nullable', 'string', 'max:500'], 'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['string', Rule::in(StaffPermissions::ALL)], 'change_reason' => [$role ? 'required' : 'nullable', 'string', 'max:500']]);
    }
}
