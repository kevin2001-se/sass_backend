<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['tenant_id', 'empresa_id', 'estado', 'nombre'], 'productos_pos_nombre_idx');
            $table->index(['tenant_id', 'empresa_id', 'estado', 'codigo_interno'], 'productos_pos_codigo_idx');
            $table->index(['tenant_id', 'empresa_id', 'categoria_id'], 'productos_pos_categoria_idx');
            $table->index(['tenant_id', 'empresa_id', 'laboratorio_id'], 'productos_pos_laboratorio_idx');
            $table->index(['tenant_id', 'empresa_id', 'principio_activo_id'], 'productos_pos_principio_idx');
        });

        Schema::table('producto_presentaciones', function (Blueprint $table) {
            $table->index(['tenant_id', 'empresa_id', 'estado', 'codigo_barra'], 'presentaciones_pos_barra_idx');
            $table->index(['tenant_id', 'empresa_id', 'producto_id', 'estado'], 'presentaciones_pos_producto_idx');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'producto_id', 'estado'], 'stocks_pos_producto_idx');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'lote_id', 'estado'], 'stocks_pos_lote_idx');
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'producto_id', 'estado', 'fecha_vencimiento'], 'lotes_pos_fefo_idx');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lotes_pos_fefo_idx');
        DB::statement('DROP INDEX IF EXISTS stocks_pos_producto_idx');
        DB::statement('DROP INDEX IF EXISTS stocks_pos_lote_idx');
        DB::statement('DROP INDEX IF EXISTS presentaciones_pos_barra_idx');
        DB::statement('DROP INDEX IF EXISTS presentaciones_pos_producto_idx');
        DB::statement('DROP INDEX IF EXISTS productos_pos_nombre_idx');
        DB::statement('DROP INDEX IF EXISTS productos_pos_codigo_idx');
        DB::statement('DROP INDEX IF EXISTS productos_pos_categoria_idx');
        DB::statement('DROP INDEX IF EXISTS productos_pos_laboratorio_idx');
        DB::statement('DROP INDEX IF EXISTS productos_pos_principio_idx');
    }
};


