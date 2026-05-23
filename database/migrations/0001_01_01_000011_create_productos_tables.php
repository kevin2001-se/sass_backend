<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('laboratorio_id')->nullable()->constrained('laboratorios')->nullOnDelete();
            $table->foreignId('principio_activo_id')->nullable()->constrained('principios_activos')->nullOnDelete();
            $table->foreignId('accion_terapeutica_id')->nullable()->constrained('acciones_terapeuticas')->nullOnDelete();
            $table->string('codigo_interno');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('concentracion')->nullable();
            $table->boolean('requiere_receta')->default(false);
            $table->boolean('maneja_lote')->default(false);
            $table->boolean('maneja_vencimiento')->default(false);
            $table->boolean('afecto_igv')->default(true);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo_interno']);
            $table->index(['tenant_id', 'empresa_id', 'estado']);
        });

        Schema::create('producto_presentaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->restrictOnDelete();
            $table->string('nombre');
            $table->string('codigo_barra')->nullable();
            $table->decimal('factor_conversion', 12, 4);
            $table->decimal('precio_compra', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2);
            $table->boolean('es_principal')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo_barra']);
            $table->index(['tenant_id', 'empresa_id', 'producto_id', 'estado'], 'pp_tenant_empresa_producto_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_presentaciones');
        Schema::dropIfExists('productos');
    }
};
