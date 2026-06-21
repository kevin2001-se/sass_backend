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
            if (! Schema::hasColumn('resumenes_diarios', 'estado')) {
                $table->string('estado', 20)->default('REGISTRADO')->after('identificador');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'total_documentos')) {
                $table->unsignedInteger('total_documentos')->default(0)->after('estado_sunat');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'total_boletas')) {
                $table->unsignedInteger('total_boletas')->default(0)->after('total_documentos');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'total_notas_credito')) {
                $table->unsignedInteger('total_notas_credito')->default(0)->after('total_boletas');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'total_notas_debito')) {
                $table->unsignedInteger('total_notas_debito')->default(0)->after('total_notas_credito');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'monto_total')) {
                $table->decimal('monto_total', 12, 2)->default(0)->after('total_notas_debito');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('observacion')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('resumenes_diarios', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('resumenes_diarios', 'anulado_by')) {
                $table->foreignId('anulado_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('resumenes_diarios', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('anulado_by');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'motivo_anulacion')) {
                $table->text('motivo_anulacion')->nullable()->after('anulado_at');
            }

            $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'resumenes_contexto_idx');
            $table->index('fecha_resumen', 'resumenes_fecha_resumen_idx');
            $table->index('identificador', 'resumenes_identificador_idx');
            $table->index('estado', 'resumenes_estado_idx');
        });

        DB::statement('ALTER TABLE resumen_diario_detalles ALTER COLUMN comprobante_electronico_id DROP NOT NULL');

        Schema::table('resumen_diario_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('resumen_diario_detalles', 'documento_id')) {
                $table->unsignedBigInteger('documento_id')->nullable()->after('resumen_diario_id');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'numero_completo')) {
                $table->string('numero_completo', 30)->nullable()->after('numero_comprobante');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'cliente_tipo_documento')) {
                $table->string('cliente_tipo_documento', 20)->nullable()->after('numero_completo');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'cliente_numero_documento')) {
                $table->string('cliente_numero_documento', 20)->nullable()->after('cliente_tipo_documento');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'cliente_nombre')) {
                $table->string('cliente_nombre')->nullable()->after('cliente_numero_documento');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('cliente_nombre');
            }
            if (! Schema::hasColumn('resumen_diario_detalles', 'estado_documento')) {
                $table->string('estado_documento', 20)->nullable()->after('total_igv');
            }

            $table->index('resumen_diario_id', 'resumen_detalles_resumen_idx');
            $table->index(['documento_id', 'tipo_documento'], 'resumen_detalles_documento_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('resumen_diario_detalles', function (Blueprint $table) {
            foreach ([
                'resumen_detalles_resumen_idx',
                'resumen_detalles_documento_tipo_idx',
            ] as $index) {
                try { $table->dropIndex($index); } catch (Throwable $e) {}
            }

            $columns = [
                'documento_id',
                'numero_completo',
                'cliente_tipo_documento',
                'cliente_numero_documento',
                'cliente_nombre',
                'subtotal',
                'estado_documento',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('resumen_diario_detalles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('resumenes_diarios', function (Blueprint $table) {
            foreach ([
                'resumenes_contexto_idx',
                'resumenes_fecha_resumen_idx',
                'resumenes_identificador_idx',
                'resumenes_estado_idx',
            ] as $index) {
                try { $table->dropIndex($index); } catch (Throwable $e) {}
            }

            $columns = [
                'estado',
                'total_documentos',
                'total_boletas',
                'total_notas_credito',
                'total_notas_debito',
                'monto_total',
                'created_by',
                'updated_by',
                'anulado_by',
                'anulado_at',
                'motivo_anulacion',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('resumenes_diarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
