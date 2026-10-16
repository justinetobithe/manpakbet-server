<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = $this->userCounts();

        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => $this->present($role, $counts[$role->id] ?? 0));

        $permissions = Permission::query()->orderBy('name')->pluck('name')->map(fn ($name) => [
            'name' => $name,
            'description' => AccessControl::PERMISSIONS[$name] ?? $name,
        ]);

        return $this->success(['roles' => $roles, 'permissions' => $permissions], 'Roles retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => strtolower($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return $this->created($this->present($role->load('permissions:id,name'), 0), 'Role created');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::query()->findOrFail($id);

        if ($role->name === AccessControl::ROLE_ADMIN) {
            return $this->error('The admin role always has every permission', 422);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return $this->success($this->present($role->load('permissions:id,name'), $this->userCounts()[$role->id] ?? 0), 'Permissions updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::query()->findOrFail($id);

        if (in_array($role->name, AccessControl::PROTECTED_ROLES, true)) {
            return $this->error('Built-in roles cannot be deleted', 422);
        }

        if (($this->userCounts()[$role->id] ?? 0) > 0) {
            return $this->error('Reassign users before deleting this role', 422);
        }

        $role->delete();

        return $this->success(null, 'Role deleted');
    }

    private function userCounts(): array
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function present(Role $role, int $usersCount): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'is_protected' => in_array($role->name, AccessControl::PROTECTED_ROLES, true),
            'users_count' => $usersCount,
            'permissions' => $role->permissions->pluck('name')->values(),
        ];
    }
}
