<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->string('xml_path')->nullable()->after('motivo_anulacion');
            $table->string('cdr_path')->nullable()->after('xml_path');
            $table->string('hash')->nullable()->after('cdr_path');
            $table->text('qr_text')->nullable()->after('hash');
            $table->string('estado_sunat', 20)->default('PENDIENTE')->after('qr_text');
            $table->string('codigo_respuesta', 20)->nullable()->after('estado_sunat');
            $table->text('mensaje_respuesta')->nullable()->after('codigo_respuesta');
            $table->unsignedInteger('intentos_envio')->default(0)->after('mensaje_respuesta');
            $table->timestamp('enviado_at')->nullable()->after('intentos_envio');
            $table->timestamp('aceptado_at')->nullable()->after('enviado_at');
            $table->timestamp('rechazado_at')->nullable()->after('aceptado_at');

            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado_sunat'], 'nc_scope_estado_sunat_index');
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropIndex('nc_scope_estado_sunat_index');
            $table->dropColumn([
                'xml_path',
                'cdr_path',
                'hash',
                'qr_text',
                'estado_sunat',
                'codigo_respuesta',
                'mensaje_respuesta',
                'intentos_envio',
                'enviado_at',
                'aceptado_at',
                'rechazado_at',
            ]);
        });
    }
};
