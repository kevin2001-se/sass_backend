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
            ['name' => 'notas_credito.enviar_sunat', 'label' => 'Enviar notas de credito a SUNAT', 'description' => 'Enviar notas de credito electronicas a SUNAT'],
            ['name' => 'notas_credito.reenviar_sunat', 'label' => 'Reenviar notas de credito a SUNAT', 'description' => 'Reintentar envio SUNAT de notas de credito'],
            ['name' => 'notas_credito.descargar_xml', 'label' => 'Descargar XML de notas de credito', 'description' => 'Descargar XML privado de notas de credito'],
            ['name' => 'notas_credito.descargar_cdr', 'label' => 'Descargar CDR de notas de credito', 'description' => 'Descargar CDR privado de notas de credito'],
            ['name' => 'notas_credito.cdr.descargar', 'label' => 'Descargar CDR de notas de credito', 'description' => 'Descargar CDR privado de notas de credito'],
            ['name' => 'notas_debito.enviar_sunat', 'label' => 'Enviar notas de debito a SUNAT', 'description' => 'Enviar notas de debito electronicas a SUNAT'],
            ['name' => 'notas_debito.reenviar_sunat', 'label' => 'Reenviar notas de debito a SUNAT', 'description' => 'Reintentar envio SUNAT de notas de debito'],
            ['name' => 'notas_debito.descargar_xml', 'label' => 'Descargar XML de notas de debito', 'description' => 'Descargar XML privado de notas de debito'],
            ['name' => 'notas_debito.descargar_cdr', 'label' => 'Descargar CDR de notas de debito', 'description' => 'Descargar CDR privado de notas de debito'],
            ['name' => 'notas_debito.cdr.descargar', 'label' => 'Descargar CDR de notas de debito', 'description' => 'Descargar CDR privado de notas de debito'],
            ['name' => 'notas_debito.xml.descargar', 'label' => 'Descargar XML de notas de debito', 'description' => 'Descargar XML privado de notas de debito'],
            ['name' => 'notas_debito.ticket.descargar', 'label' => 'Descargar ticket de notas de debito', 'description' => 'Descargar ticket 80mm privado de notas de debito'],
            ['name' => 'notas_debito.ticket.generar', 'label' => 'Generar ticket de notas de debito', 'description' => 'Generar ticket 80mm privado de notas de debito'],
            ['name' => 'notas_debito.pdf.descargar', 'label' => 'Descargar PDF de notas de debito', 'description' => 'Descargar PDF A4 privado de notas de debito'],
            ['name' => 'notas_debito.pdf.generar', 'label' => 'Generar PDF de notas de debito', 'description' => 'Generar PDF A4 privado de notas de debito'],
            ['name' => 'notas_credito.xml.descargar', 'label' => 'Descargar XML de notas de credito', 'description' => 'Descargar XML privado de notas de credito'],
            ['name' => 'notas_credito.ticket.descargar', 'label' => 'Descargar ticket de notas de credito', 'description' => 'Descargar ticket 80mm privado de notas de credito'],
            ['name' => 'notas_credito.ticket.generar', 'label' => 'Generar ticket de notas de credito', 'description' => 'Generar ticket 80mm privado de notas de credito'],
            ['name' => 'notas_credito.pdf.descargar', 'label' => 'Descargar PDF de notas de credito', 'description' => 'Descargar PDF A4 privado de notas de credito'],
            ['name' => 'notas_credito.pdf.generar', 'label' => 'Generar PDF de notas de credito', 'description' => 'Generar PDF A4 privado de notas de credito'],
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

