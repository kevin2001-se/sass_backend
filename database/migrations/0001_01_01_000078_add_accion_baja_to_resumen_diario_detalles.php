<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resumen_diario_detalles')) {
            return;
        }

        Schema::table('resumen_diario_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('resumen_diario_detalles', 'accion')) {
                $table->string('accion', 10)->default('ALTA')->after('estado_item');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'estado_baja')) {
                $table->string('estado_baja', 20)->nullable()->after('estado_documento');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'motivo_baja')) {
                $table->text('motivo_baja')->nullable()->after('estado_baja');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('resumen_diario_detalles')) {
            return;
        }

        Schema::table('resumen_diario_detalles', function (Blueprint $table) {
            foreach (['motivo_baja', 'estado_baja', 'accion'] as $column) {
                if (Schema::hasColumn('resumen_diario_detalles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
