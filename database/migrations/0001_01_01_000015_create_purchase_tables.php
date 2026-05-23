<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_documento', 20);
            $table->string('numero_documento')->nullable();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_documento']);
            $table->index(['tenant_id', 'empresa_id', 'estado']);
            $table->index(['tipo_documento', 'numero_documento']);
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo_comprobante', 20);
            $table->string('serie', 20);
            $table->string('numero', 30);
            $table->string('tipo_compra', 20);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total_igv', 14, 2);
            $table->decimal('total_descuento', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('estado', 20)->default('REGISTRADA');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'proveedor_id', 'tipo_comprobante', 'serie', 'numero'], 'compras_documento_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'compras_scope_estado_index');
            $table->index(['proveedor_id', 'fecha_emision']);
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
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

            $table->index(['tenant_id', 'empresa_id', 'compra_id']);
            $table->index(['producto_id', 'lote_id']);
        });

        Schema::create('compra_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('metodo_pago', 20);
            $table->decimal('monto', 14, 2);
            $table->string('referencia')->nullable();
            $table->string('estado', 20)->default('REGISTRADO');
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'compra_id']);
            $table->index(['metodo_pago', 'estado']);
        });

        Schema::create('cuentas_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->decimal('monto_total', 14, 2);
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->timestamps();

            $table->unique(['empresa_id', 'compra_id']);
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'cxp_scope_estado_index');
            $table->index(['proveedor_id', 'fecha_vencimiento']);
        });

        Schema::create('cuentas_por_pagar_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('metodo_pago', 20);
            $table->decimal('monto', 14, 2);
            $table->date('fecha_pago');
            $table->string('referencia')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('REGISTRADO');
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'tienda_id']);
            $table->index(['cuenta_por_pagar_id', 'fecha_pago'], 'cxp_pagos_cuenta_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_pagar_pagos');
        Schema::dropIfExists('cuentas_por_pagar');
        Schema::dropIfExists('compra_pagos');
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};
