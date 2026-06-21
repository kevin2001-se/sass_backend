<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comunicaciones_baja')) {
            Schema::table('comunicaciones_baja', function (Blueprint $table) {
                if (! Schema::hasColumn('comunicaciones_baja', 'estado')) {
                    $table->string('estado', 20)->default('REGISTRADA')->after('identificador');
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'total_documentos')) {
                    $table->unsignedInteger('total_documentos')->default(0)->after('estado_sunat');
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('observacion')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'anulado_by')) {
                    $table->foreignId('anulado_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'anulado_at')) {
                    $table->timestamp('anulado_at')->nullable()->after('anulado_by');
                }
                if (! Schema::hasColumn('comunicaciones_baja', 'motivo_anulacion')) {
                    $table->text('motivo_anulacion')->nullable()->after('anulado_at');
                }
            });
        }

        if (Schema::hasTable('comunicacion_baja_detalles')) {
            Schema::table('comunicacion_baja_detalles', function (Blueprint $table) {
                if (! Schema::hasColumn('comunicacion_baja_detalles', 'comprobante_id')) {
                    $table->foreignId('comprobante_id')->nullable()->after('comunicacion_baja_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
                }
                if (! Schema::hasColumn('comunicacion_baja_detalles', 'numero_completo')) {
                    $table->string('numero_completo', 30)->nullable()->after('numero_comprobante');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comunicacion_baja_detalles')) {
            Schema::table('comunicacion_baja_detalles', function (Blueprint $table) {
                if (Schema::hasColumn('comunicacion_baja_detalles', 'comprobante_id')) {
                    $table->dropConstrainedForeignId('comprobante_id');
                }
                if (Schema::hasColumn('comunicacion_baja_detalles', 'numero_completo')) {
                    $table->dropColumn('numero_completo');
                }
            });
        }

        if (Schema::hasTable('comunicaciones_baja')) {
            Schema::table('comunicaciones_baja', function (Blueprint $table) {
                foreach (['motivo_anulacion', 'anulado_at', 'total_documentos', 'estado'] as $column) {
                    if (Schema::hasColumn('comunicaciones_baja', $column)) {
                        $table->dropColumn($column);
                    }
                }
                foreach (['anulado_by', 'updated_by', 'created_by'] as $column) {
                    if (Schema::hasColumn('comunicaciones_baja', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }
    }
};
