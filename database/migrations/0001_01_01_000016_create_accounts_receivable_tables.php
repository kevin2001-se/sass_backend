<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_por_cobrar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->decimal('monto_total', 14, 2);
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'venta_id']);
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'cxc_scope_estado_index');
            $table->index(['cliente_id', 'fecha_vencimiento']);
        });

        Schema::create('cuentas_por_cobrar_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('cuenta_por_cobrar_id')->constrained('cuentas_por_cobrar')->cascadeOnDelete();
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
            $table->index(['cuenta_por_cobrar_id', 'fecha_pago'], 'cxc_pagos_cuenta_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_cobrar_pagos');
        Schema::dropIfExists('cuentas_por_cobrar');
    }
};
