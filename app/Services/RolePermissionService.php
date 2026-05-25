<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionService
{
    public function listarRoles(Request $request)
    {
        return Role::with('permissions')
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'ILIKE', '%'.trim($request->input('q')).'%'))
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));
    }

    public function listarPermisos()
    {
        return Permission::where('active', true)->orderBy('name')->get();
    }

    public function crear(Request $request, array $data): Role
    {
        return DB::transaction(function () use ($request, $data) {
            $role = Role::create([
                'empresa_id' => $request->attributes->get('empresa')->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'active' => $data['active'] ?? true,
            ]);
            $role->permissions()->sync($data['permissions'] ?? []);
            return $role->load('permissions');
        });
    }

    public function actualizar(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'active' => $data['active'] ?? $role->active,
            ]);
            $role->permissions()->sync($data['permissions'] ?? []);
            return $role->refresh()->load('permissions');
        });
    }

    public function findScoped(Request $request, int $id): Role
    {
        return Role::with('permissions')->where('empresa_id', $request->attributes->get('empresa')->id)->findOrFail($id);
    }
}