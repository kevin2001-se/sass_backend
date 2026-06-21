<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            return;
        }

        Schema::table('comunicaciones_baja', function (Blueprint $table) {
            if (! Schema::hasColumn('comunicaciones_baja', 'ticket_sunat')) {
                $table->string('ticket_sunat', 100)->nullable()->after('ticket');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'hash')) {
                $table->string('hash', 128)->nullable()->after('cdr_path');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'intentos_envio')) {
                $table->unsignedInteger('intentos_envio')->default(0)->after('mensaje_respuesta');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('intentos_envio');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('ticket_sunat');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'estado_sunat')) {
                $table->string('estado_sunat', 20)->default('PENDIENTE')->after('estado');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'codigo_respuesta')) {
                $table->string('codigo_respuesta', 50)->nullable()->after('hash');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'mensaje_respuesta')) {
                $table->text('mensaje_respuesta')->nullable()->after('codigo_respuesta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            return;
        }

        Schema::table('comunicaciones_baja', function (Blueprint $table) {
            foreach (['ticket_sunat', 'hash'] as $column) {
                if (Schema::hasColumn('comunicaciones_baja', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
