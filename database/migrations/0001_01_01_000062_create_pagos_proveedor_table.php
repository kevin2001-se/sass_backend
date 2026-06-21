<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagos_proveedor')) {
            Schema::create('pagos_proveedor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
                $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
                $table->string('metodo_pago', 20);
                $table->decimal('monto', 14, 2);
                $table->string('referencia')->nullable();
                $table->date('fecha_pago');
                $table->text('observacion')->nullable();
                $table->string('estado', 20)->default('REGISTRADO');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('anulado_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('anulado_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'pagos_proveedor_scope_estado_idx');
                $table->index(['cuenta_por_pagar_id', 'fecha_pago'], 'pagos_proveedor_cuenta_fecha_idx');
                $table->index(['proveedor_id', 'fecha_pago'], 'pagos_proveedor_proveedor_fecha_idx');
            });
        }

        if (Schema::hasTable('cuentas_por_pagar_pagos')) {
            $exists = DB::table('pagos_proveedor')->exists();
            if (! $exists) {
                $pagos = DB::table('cuentas_por_pagar_pagos')
                    ->join('cuentas_por_pagar', 'cuentas_por_pagar.id', '=', 'cuentas_por_pagar_pagos.cuenta_por_pagar_id')
                    ->select('cuentas_por_pagar_pagos.*', 'cuentas_por_pagar.proveedor_id')
                    ->get();

                foreach ($pagos as $pago) {
                    DB::table('pagos_proveedor')->insert([
                        'tenant_id' => $pago->tenant_id,
                        'empresa_id' => $pago->empresa_id,
                        'tienda_id' => $pago->tienda_id,
                        'cuenta_por_pagar_id' => $pago->cuenta_por_pagar_id,
                        'proveedor_id' => $pago->proveedor_id,
                        'caja_id' => $pago->caja_id,
                        'metodo_pago' => $pago->metodo_pago,
                        'monto' => $pago->monto,
                        'referencia' => $pago->referencia,
                        'fecha_pago' => $pago->fecha_pago,
                        'observacion' => $pago->observacion,
                        'estado' => $pago->estado === 'ANULADO' ? 'ANULADO' : 'REGISTRADO',
                        'created_by' => $pago->user_id,
                        'created_at' => $pago->created_at,
                        'updated_at' => $pago->updated_at,
                    ]);
                }
            }
        }

        $now = now();
        foreach ([
            'pagos_proveedor.ver' => 'Ver pagos proveedor',
            'pagos_proveedor.crear' => 'Crear pagos proveedor',
            'pagos_proveedor.anular' => 'Anular pagos proveedor',
        ] as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'description' => $label, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $this->syncToRolesWith('compras.ver', 'pagos_proveedor.ver');
        $this->syncToRolesWith('compras.crear', 'pagos_proveedor.crear');
        $this->syncToRolesWith('compras.anular', 'pagos_proveedor.anular');
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedor');

        $ids = DB::table('permissions')->whereIn('name', ['pagos_proveedor.ver', 'pagos_proveedor.crear', 'pagos_proveedor.anular'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    private function syncToRolesWith(string $sourcePermission, string $targetPermission): void
    {
        $sourceId = DB::table('permissions')->where('name', $sourcePermission)->value('id');
        $targetId = DB::table('permissions')->where('name', $targetPermission)->value('id');

        if (! $sourceId || ! $targetId) {
            return;
        }

        $roleIds = DB::table('permission_role')->where('permission_id', $sourceId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $targetId],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};