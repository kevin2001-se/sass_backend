<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_documento', 20);
            $table->string('numero_documento')->nullable();
            $table->string('nombres');
            $table->string('razon_social')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_documento']);
            $table->index(['tenant_id', 'empresa_id', 'estado']);
            $table->index(['tipo_documento', 'numero_documento']);
        });

        Schema::create('series_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->string('tipo_comprobante', 20);
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo_actual')->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'tipo_comprobante', 'serie'], 'series_empresa_tienda_tipo_serie_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'series_scope_estado_index');
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo_comprobante', 20);
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->string('numero_comprobante');
            $table->string('tipo_venta', 20);
            $table->dateTime('fecha_emision');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total_igv', 14, 2);
            $table->decimal('total_descuento', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('estado', 20)->default('REGISTRADA');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'tipo_comprobante', 'serie', 'correlativo'], 'ventas_comprobante_unique');
            $table->unique(['empresa_id', 'numero_comprobante']);
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'ventas_scope_estado_index');
            $table->index(['cliente_id', 'fecha_emision']);
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('producto_presentacion_id')->constrained('producto_presentaciones')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad_presentacion', 14, 4);
            $table->decimal('factor_conversion', 12, 4);
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->boolean('afecto_igv')->default(true);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('igv', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'venta_id']);
            $table->index(['producto_id', 'lote_id']);
        });

        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('metodo_pago', 20);
            $table->decimal('monto', 14, 2);
            $table->string('referencia')->nullable();
            $table->string('estado', 20)->default('REGISTRADO');
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'venta_id']);
            $table->index(['metodo_pago', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('series_comprobantes');
        Schema::dropIfExists('clientes');
    }
};
