<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_por_pagar')) {
            Schema::create('cuentas_por_pagar', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
                $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
                $table->date('fecha_emision');
                $table->date('fecha_vencimiento')->nullable();
                $table->decimal('monto_total', 14, 2);
                $table->decimal('monto_pagado', 14, 2)->default(0);
                $table->decimal('saldo', 14, 2);
                $table->string('estado', 20)->default('PENDIENTE');
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'compra_id']);
                $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'cxp_scope_estado_index');
                $table->index(['proveedor_id', 'fecha_vencimiento']);
            });
        } else {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                if (! Schema::hasColumn('cuentas_por_pagar', 'observacion')) {
                    $table->text('observacion')->nullable()->after('estado');
                }
            });
        }

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'cuentas_pagar.ver'],
            ['label' => 'Ver cuentas por pagar', 'description' => 'Ver cuentas por pagar', 'active' => true, 'updated_at' => $now, 'created_at' => $now]
        );

        $this->syncToRolesWith('compras.ver', 'cuentas_pagar.ver');
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentas_por_pagar') && Schema::hasColumn('cuentas_por_pagar', 'observacion')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }

        $id = DB::table('permissions')->where('name', 'cuentas_pagar.ver')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
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