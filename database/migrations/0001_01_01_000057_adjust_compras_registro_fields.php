<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (! Schema::hasColumn('compras', 'moneda')) {
                $table->string('moneda', 3)->default('PEN')->after('tipo_compra');
            }
            if (! Schema::hasColumn('compras', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('observacion')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('compras', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('compras', 'anulado_by')) {
                $table->foreignId('anulado_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('compras', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('anulado_by');
            }
            if (! Schema::hasColumn('compras', 'motivo_anulacion')) {
                $table->text('motivo_anulacion')->nullable()->after('anulado_at');
            }
        });

        Schema::table('compra_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('compra_detalles', 'fecha_vencimiento')) {
                $table->date('fecha_vencimiento')->nullable()->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compra_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('compra_detalles', 'fecha_vencimiento')) {
                $table->dropColumn('fecha_vencimiento');
            }
        });

        Schema::table('compras', function (Blueprint $table) {
            foreach (['motivo_anulacion', 'anulado_at'] as $column) {
                if (Schema::hasColumn('compras', $column)) {
                    $table->dropColumn($column);
                }
            }
            foreach (['anulado_by', 'updated_by', 'created_by'] as $column) {
                if (Schema::hasColumn('compras', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            if (Schema::hasColumn('compras', 'moneda')) {
                $table->dropColumn('moneda');
            }
        });
    }
};