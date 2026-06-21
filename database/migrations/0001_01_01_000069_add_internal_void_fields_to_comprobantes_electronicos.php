<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobantes_electronicos', 'estado_baja')) {
                $table->string('estado_baja', 20)->default('SIN_BAJA')->after('estado_sunat');
            }
            if (! Schema::hasColumn('comprobantes_electronicos', 'motivo_baja')) {
                $table->text('motivo_baja')->nullable()->after('estado_baja');
            }
            if (! Schema::hasColumn('comprobantes_electronicos', 'fecha_solicitud_baja')) {
                $table->timestamp('fecha_solicitud_baja')->nullable()->after('motivo_baja');
            }
            if (! Schema::hasColumn('comprobantes_electronicos', 'solicitado_baja_por')) {
                $table->foreignId('solicitado_baja_por')->nullable()->after('fecha_solicitud_baja')->constrained('users')->nullOnDelete();
            }

            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado_baja'], 'ce_scope_estado_baja_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            try { $table->dropIndex('ce_scope_estado_baja_idx'); } catch (Throwable $e) {}
            foreach (['solicitado_baja_por', 'fecha_solicitud_baja', 'motivo_baja', 'estado_baja'] as $column) {
                if (Schema::hasColumn('comprobantes_electronicos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};