<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            if (! Schema::hasColumn('guias_remision', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('observacion');
            }
            if (! Schema::hasColumn('guias_remision', 'cdr_path')) {
                $table->string('cdr_path')->nullable()->after('xml_path');
            }
            if (! Schema::hasColumn('guias_remision', 'hash')) {
                $table->string('hash')->nullable()->after('cdr_path');
            }
            if (! Schema::hasColumn('guias_remision', 'qr_text')) {
                $table->text('qr_text')->nullable()->after('hash');
            }
            if (! Schema::hasColumn('guias_remision', 'estado_sunat')) {
                $table->string('estado_sunat', 20)->default('PENDIENTE')->after('qr_text');
            }
            if (! Schema::hasColumn('guias_remision', 'codigo_respuesta')) {
                $table->string('codigo_respuesta', 20)->nullable()->after('estado_sunat');
            }
            if (! Schema::hasColumn('guias_remision', 'mensaje_respuesta')) {
                $table->text('mensaje_respuesta')->nullable()->after('codigo_respuesta');
            }
            if (! Schema::hasColumn('guias_remision', 'intentos_envio')) {
                $table->unsignedInteger('intentos_envio')->default(0)->after('mensaje_respuesta');
            }
            if (! Schema::hasColumn('guias_remision', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('intentos_envio');
            }
            if (! Schema::hasColumn('guias_remision', 'aceptado_at')) {
                $table->timestamp('aceptado_at')->nullable()->after('enviado_at');
            }
            if (! Schema::hasColumn('guias_remision', 'rechazado_at')) {
                $table->timestamp('rechazado_at')->nullable()->after('aceptado_at');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_estado_sunat_idx ON guias_remision (estado_sunat)');
        }
    }

    public function down(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            foreach ([
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
            ] as $column) {
                if (Schema::hasColumn('guias_remision', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};