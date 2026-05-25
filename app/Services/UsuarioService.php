<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function listar(Request $request)
    {
        return User::with(['role.permissions', 'tiendas', 'tiendaActiva'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim($request->input('q'));
                $query->where(fn ($sub) => $sub->where('name', 'ILIKE', "%{$q}%")->orWhere('email', 'ILIKE', "%{$q}%"));
            })
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));
    }

    public function crear(Request $request, array $data): User
    {
        return DB::transaction(function () use ($request, $data) {
            $empresa = $request->attributes->get('empresa');
            $tenant = $request->attributes->get('tenant');
            $tiendaActiva = $data['tienda_activa_id'] ?? ($data['tiendas'][0] ?? null);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresa->id,
                'tienda_activa_id' => $tiendaActiva,
                'role_id' => $data['roles'][0],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'estado' => $data['estado'] ?? true,
            ]);

            $this->syncTiendas($user, $data['tiendas'], $tenant->id, $empresa->id);
            return $user->load(['role.permissions', 'tiendas', 'tiendaActiva']);
        });
    }

    public function actualizar(Request $request, User $user, array $data): User
    {
        return DB::transaction(function () use ($request, $user, $data) {
            $update = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['roles'][0],
                'tienda_activa_id' => $data['tienda_activa_id'] ?? ($data['tiendas'][0] ?? $user->tienda_activa_id),
                'estado' => $data['estado'] ?? $user->estado ?? true,
            ];

            if (! empty($data['password'])) {
                $update['password'] = Hash::make($data['password']);
            }

            $user->update($update);
            $this->syncTiendas($user, $data['tiendas'], $request->attributes->get('tenant')->id, $request->attributes->get('empresa')->id);
            return $user->refresh()->load(['role.permissions', 'tiendas', 'tiendaActiva']);
        });
    }

    public function findScoped(Request $request, int $id): User
    {
        return User::with(['role.permissions', 'tiendas', 'tiendaActiva'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }

    private function syncTiendas(User $user, array $tiendaIds, int $tenantId, int $empresaId): void
    {
        $sync = [];
        foreach ($tiendaIds as $id) {
            $sync[$id] = ['tenant_id' => $tenantId, 'empresa_id' => $empresaId, 'estado' => true];
        }
        $user->tiendas()->sync($sync);
    }
}