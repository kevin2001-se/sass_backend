<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('codigo_lote');
            $table->date('fecha_vencimiento')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'producto_id', 'codigo_lote'], 'lotes_empresa_tienda_producto_codigo_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'lotes_tenant_empresa_tienda_estado_index');
            $table->index(['producto_id', 'fecha_vencimiento'], 'lotes_producto_vencimiento_index');
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->cascadeOnDelete();
            $table->decimal('cantidad_actual', 14, 4)->default(0);
            $table->decimal('cantidad_minima', 14, 4)->nullable();
            $table->decimal('cantidad_maxima', 14, 4)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'producto_id', 'lote_id'], 'stocks_empresa_tienda_producto_lote_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'stocks_tenant_empresa_tienda_estado_index');
            $table->index(['producto_id', 'lote_id'], 'stocks_producto_lote_index');
        });

        DB::statement('CREATE UNIQUE INDEX stocks_empresa_tienda_producto_sin_lote_unique ON stocks (empresa_id, tienda_id, producto_id) WHERE lote_id IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_cantidad_actual_no_negativa CHECK (cantidad_actual >= 0)');
        }

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('producto_presentacion_id')->constrained('producto_presentaciones')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->string('tipo_movimiento', 30);
            $table->string('motivo');
            $table->decimal('cantidad_presentacion', 14, 4);
            $table->decimal('factor_conversion', 12, 4);
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('stock_anterior', 14, 4);
            $table->decimal('stock_nuevo', 14, 4);
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'producto_id'], 'im_scope_producto_index');
            $table->index(['producto_id', 'lote_id', 'created_at'], 'im_producto_lote_fecha_index');
            $table->index(['tipo_movimiento', 'created_at'], 'im_tipo_fecha_index');
            $table->index(['referencia_tipo', 'referencia_id'], 'im_referencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('lotes');
    }
};
