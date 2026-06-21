<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            if (! Schema::hasColumn('resumenes_diarios', 'ticket_sunat')) {
                $table->string('ticket_sunat')->nullable()->after('ticket');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'hash')) {
                $table->string('hash')->nullable()->after('xml_path');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('ticket_sunat');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'estado_sunat')) {
                $table->string('estado_sunat', 20)->default('PENDIENTE')->after('estado');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'codigo_respuesta')) {
                $table->string('codigo_respuesta')->nullable()->after('hash');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'mensaje_respuesta')) {
                $table->text('mensaje_respuesta')->nullable()->after('codigo_respuesta');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'intentos_envio')) {
                $table->unsignedInteger('intentos_envio')->default(0)->after('mensaje_respuesta');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('intentos_envio');
            }

            $table->index('estado_sunat', 'resumenes_estado_sunat_idx');
            $table->index('ticket_sunat', 'resumenes_ticket_sunat_idx');
        });

        if (Schema::hasColumn('resumenes_diarios', 'ticket')) {
            DB::table('resumenes_diarios')
                ->whereNull('ticket_sunat')
                ->whereNotNull('ticket')
                ->update(['ticket_sunat' => DB::raw('ticket')]);
        }
    }

    public function down(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            foreach (['resumenes_estado_sunat_idx', 'resumenes_ticket_sunat_idx'] as $index) {
                try { $table->dropIndex($index); } catch (Throwable $e) {}
            }

            foreach (['ticket_sunat', 'hash'] as $column) {
                if (Schema::hasColumn('resumenes_diarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
