<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_nota_credito', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->string('descripcion');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('comprobante_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->string('numero_completo', 30);
            $table->string('motivo_codigo', 2);
            $table->string('motivo_descripcion')->nullable();
            $table->string('tipo_nota', 20);
            $table->boolean('afecta_stock')->default(false);
            $table->boolean('afecta_caja')->default(false);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_descuento', 14, 2)->default(0);
            $table->decimal('total_igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('REGISTRADA');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anulado_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'serie', 'correlativo'], 'nc_numero_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'nc_scope_index');
            $table->index('comprobante_id', 'nc_comprobante_index');
            $table->index('venta_id', 'nc_venta_index');
            $table->index('numero_completo', 'nc_numero_completo_index');
            $table->index('estado', 'nc_estado_index');
        });

        Schema::create('nota_credito_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('nota_credito_id')->constrained('notas_credito')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('descripcion');
            $table->string('unidad_medida', 10)->default('NIU');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('igv', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'nota_credito_id'], 'ncd_scope_index');
            $table->index('venta_detalle_id', 'ncd_venta_detalle_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_detalles');
        Schema::dropIfExists('notas_credito');
        Schema::dropIfExists('motivos_nota_credito');
    }
};
