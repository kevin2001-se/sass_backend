<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_nota_debito', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->string('descripcion');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('notas_debito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tienda_id')->constrained('tiendas');
            $table->foreignId('venta_id')->constrained('ventas');
            $table->foreignId('comprobante_id')->constrained('comprobantes_electronicos');
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->string('numero_completo');
            $table->string('motivo_codigo', 2);
            $table->string('motivo_descripcion')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('afecta_caja')->default(false);
            $table->boolean('caja_aplicada')->default(false);
            $table->timestamp('caja_aplicada_at')->nullable();
            $table->foreignId('caja_movimiento_id')->nullable()->constrained('caja_movimientos');
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('REGISTRADA');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('anulado_by')->nullable()->constrained('users');
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'nd_scope_idx');
            $table->index('comprobante_id', 'nd_comprobante_idx');
            $table->index('venta_id', 'nd_venta_idx');
            $table->index('numero_completo', 'nd_numero_idx');
            $table->index('estado', 'nd_estado_idx');
        });

        Schema::create('nota_debito_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('nota_debito_id')->constrained('notas_debito')->cascadeOnDelete();
            $table->string('descripcion', 500);
            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'nota_debito_id'], 'ndd_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_debito_detalles');
        Schema::dropIfExists('notas_debito');
        Schema::dropIfExists('motivos_nota_debito');
    }
};