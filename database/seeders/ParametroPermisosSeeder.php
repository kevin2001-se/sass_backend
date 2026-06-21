<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ParametroPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'parametros.ver', 'label' => 'Ver parametros', 'description' => 'Consultar parametros del sistema'],
            ['name' => 'parametros.editar', 'label' => 'Editar parametros', 'description' => 'Actualizar parametros del sistema'],
        ])->map(fn (array $permission) => Permission::updateOrCreate(
            ['name' => $permission['name']],
            array_merge($permission, ['active' => true])
        ));

        $permissionIds = $permissions->pluck('id')->all();

        Role::whereIn('name', ['Administrador', 'Supervisor'])->get()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }
}