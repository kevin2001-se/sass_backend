<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            if (! Schema::hasColumn('tiendas', 'ubigeo')) {
                $table->string('ubigeo', 6)->nullable()->after('direccion');
            }
        });

        Schema::table('guias_remision', function (Blueprint $table) {
            if (! Schema::hasColumn('guias_remision', 'venta_id')) {
                $table->foreignId('venta_id')->nullable()->after('tienda_id')->constrained('ventas')->nullOnDelete();
            }
            if (! Schema::hasColumn('guias_remision', 'comprobante_id')) {
                $table->foreignId('comprobante_id')->nullable()->after('venta_id')->constrained('comprobantes_electronicos')->nullOnDelete();
            }
            if (! Schema::hasColumn('guias_remision', 'tipo_referencia')) {
                $table->string('tipo_referencia', 30)->nullable()->after('comprobante_id');
            }
            if (! Schema::hasColumn('guias_remision', 'referencia_serie')) {
                $table->string('referencia_serie', 10)->nullable()->after('tipo_referencia');
            }
            if (! Schema::hasColumn('guias_remision', 'referencia_numero')) {
                $table->string('referencia_numero', 20)->nullable()->after('referencia_serie');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_venta_id_idx ON guias_remision (venta_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_comprobante_id_idx ON guias_remision (comprobante_id)');
        }
    }

    public function down(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            if (Schema::hasColumn('guias_remision', 'comprobante_id')) {
                $table->dropConstrainedForeignId('comprobante_id');
            }
            foreach (['tipo_referencia', 'referencia_serie', 'referencia_numero'] as $column) {
                if (Schema::hasColumn('guias_remision', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tiendas', function (Blueprint $table) {
            if (Schema::hasColumn('tiendas', 'ubigeo')) {
                $table->dropColumn('ubigeo');
            }
        });
    }
};