<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras') || ! Schema::hasTable('cuentas_por_pagar')) {
            return;
        }

        $now = now();
        $compras = DB::table('compras')
            ->leftJoin('cuentas_por_pagar', 'cuentas_por_pagar.compra_id', '=', 'compras.id')
            ->whereNull('cuentas_por_pagar.id')
            ->where('compras.tipo_compra', 'CREDITO')
            ->where('compras.estado', 'REGISTRADA')
            ->select('compras.*')
            ->get();

        foreach ($compras as $compra) {
            DB::table('cuentas_por_pagar')->insert([
                'tenant_id' => $compra->tenant_id,
                'empresa_id' => $compra->empresa_id,
                'tienda_id' => $compra->tienda_id,
                'proveedor_id' => $compra->proveedor_id,
                'compra_id' => $compra->id,
                'fecha_emision' => $compra->fecha_emision,
                'fecha_vencimiento' => $compra->fecha_vencimiento,
                'monto_total' => $compra->total,
                'monto_pagado' => 0,
                'saldo' => $compra->total,
                'estado' => 'PENDIENTE',
                'observacion' => $compra->observacion,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No se eliminan cuentas por pagar para no borrar historial financiero creado automaticamente.
    }
};