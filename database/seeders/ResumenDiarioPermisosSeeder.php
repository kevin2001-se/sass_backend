<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ResumenDiarioPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'resumenes_diarios.ver' => 'Ver resumenes diarios',
            'resumenes_diarios.generar' => 'Generar resumenes diarios',
            'resumenes_diarios.anular' => 'Anular resumenes diarios',
            'resumenes_diarios.enviar_sunat' => 'Enviar resumenes diarios a SUNAT',
            'resumenes_diarios.reenviar_sunat' => 'Reenviar resumenes diarios a SUNAT',
            'resumenes_diarios.descargar_xml' => 'Descargar XML de resumenes diarios',
            'resumenes_diarios.consultar_ticket' => 'Consultar ticket de resumenes diarios',
            'resumenes_diarios.descargar_cdr' => 'Descargar CDR de resumenes diarios',
            'resumenes_diarios.pdf.generar' => 'Generar PDF de resumenes diarios',
            'resumenes_diarios.pdf.descargar' => 'Descargar PDF de resumenes diarios',
            'resumenes_diarios.xml.descargar' => 'Descargar XML de resumenes diarios',
            'resumenes_diarios.cdr.descargar' => 'Descargar CDR de resumenes diarios',
        ];

        $ids = [];
        foreach ($permisos as $name => $label) {
            $permission = Permission::updateOrCreate(
                ['name' => $name],
                ['label' => $label, 'description' => $label, 'active' => true]
            );
            $ids[] = $permission->id;
        }

        Role::whereIn('name', ['Administrador', 'Supervisor'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }
}
