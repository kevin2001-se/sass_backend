<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ComprobantesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'sunat.ver', 'label' => 'Ver SUNAT', 'description' => 'Acceder a documentos y configuracion SUNAT'],
            ['name' => 'sunat.configuracion.ver', 'label' => 'Ver configuracion SUNAT', 'description' => 'Consultar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.crear', 'label' => 'Crear configuracion SUNAT', 'description' => 'Registrar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.editar', 'label' => 'Editar configuracion SUNAT', 'description' => 'Actualizar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.eliminar', 'label' => 'Eliminar configuracion SUNAT', 'description' => 'Desactivar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.comprobantes.ver', 'label' => 'Ver comprobantes electronicos', 'description' => 'Consultar boletas, facturas y comprobantes electronicos'],
            ['name' => 'sunat.comprobantes.emitir', 'label' => 'Emitir comprobantes electronicos', 'description' => 'Enviar comprobantes electronicos a SUNAT'],
            ['name' => 'sunat.comprobantes.reenviar', 'label' => 'Reenviar comprobantes electronicos', 'description' => 'Reintentar envio de comprobantes electronicos'],
            ['name' => 'sunat.documentos.descargar', 'label' => 'Descargar documentos SUNAT', 'description' => 'Descargar PDF, tickets, XML y CDR'],
            ['name' => 'sunat.notas.ver', 'label' => 'Ver notas electronicas', 'description' => 'Consultar notas de credito y debito'],
            ['name' => 'sunat.notas.crear', 'label' => 'Crear notas electronicas', 'description' => 'Generar notas de credito y debito'],
            ['name' => 'sunat.resumenes.ver', 'label' => 'Ver resumenes diarios', 'description' => 'Consultar resumenes diarios de boletas'],
            ['name' => 'sunat.resumenes.generar', 'label' => 'Generar resumenes diarios', 'description' => 'Generar, enviar y consultar resumenes diarios'],
            ['name' => 'sunat.bajas.ver', 'label' => 'Ver comunicaciones de baja', 'description' => 'Consultar comunicaciones de baja'],
            ['name' => 'sunat.bajas.generar', 'label' => 'Generar comunicaciones de baja', 'description' => 'Generar, enviar y consultar comunicaciones de baja'],
            ['name' => 'sunat.guias.ver', 'label' => 'Ver guias de remision', 'description' => 'Consultar guias de remision electronicas'],
            ['name' => 'sunat.guias.crear', 'label' => 'Crear guias de remision', 'description' => 'Generar y enviar guias de remision electronicas'],
        ])->map(fn (array $permission) => Permission::updateOrCreate(
            ['name' => $permission['name']],
            array_merge($permission, ['active' => true])
        ));

        $allSunat = $permissions->pluck('id')->all();
        $readOnlySunat = Permission::whereIn('name', [
            'sunat.ver',
            'sunat.comprobantes.ver',
            'sunat.documentos.descargar',
            'sunat.notas.ver',
            'sunat.resumenes.ver',
            'sunat.bajas.ver',
            'sunat.guias.ver',
        ])->pluck('id')->all();

        Role::where('name', 'Administrador')->get()->each(function (Role $role) use ($allSunat) {
            $role->permissions()->syncWithoutDetaching($allSunat);
        });

        Role::where('name', 'Supervisor')->get()->each(function (Role $role) use ($allSunat) {
            $role->permissions()->syncWithoutDetaching($allSunat);
        });

        Role::where('name', 'Cajero')->get()->each(function (Role $role) use ($readOnlySunat) {
            $role->permissions()->syncWithoutDetaching($readOnlySunat);
        });
    }
}

