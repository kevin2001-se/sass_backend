<?php

namespace App\Services\Reportes;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\CuentaPorCobrar;
use App\Models\CuentaPorPagar;
use App\Models\Stock;
use App\Models\Venta;

class DashboardService
{
    public function obtenerResumen(array $filtros): array
    {
        $hoy = today();
        $diasAlertaVencimiento = (int) parametro('dias_alerta_vencimiento', 30);
        $scope = fn ($q) => $q->where('tenant_id', $filtros['tenant_id'])
            ->where('empresa_id', $filtros['empresa_id'])
            ->where('tienda_id', $filtros['tienda_id']);

        $ventasDia = (float) Venta::query()->tap($scope)->whereDate('fecha_emision', $hoy)->where('estado', Venta::REGISTRADA)->sum('total');
        $comprasDia = (float) Compra::query()->tap($scope)->whereDate('fecha_emision', $hoy)->where('estado', Compra::REGISTRADA)->sum('total');

        $caja = Caja::query()->tap($scope)->where('estado', Caja::ABIERTA)->first();
        $lotesPorVencer = Stock::query()
            ->tap($scope)
            ->whereNotNull('lote_id')
            ->where('cantidad_actual', '>', 0)
            ->whereHas('lote', fn ($query) => $query
                ->where('estado', true)
                ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays($diasAlertaVencimiento)]))
            ->distinct('lote_id')
            ->count('lote_id');

        return [
            'ventas_dia' => round($ventasDia, 2),
            'compras_dia' => round($comprasDia, 2),
            'utilidad_estimada' => round($ventasDia - $comprasDia, 2),
            'productos_bajo_stock' => Stock::query()->tap($scope)->whereNotNull('cantidad_minima')->whereColumn('cantidad_actual', '<=', 'cantidad_minima')->count(),
            'productos_por_vencer' => $lotesPorVencer,
            'cuentas_por_cobrar_vencidas' => CuentaPorCobrar::query()->tap($scope)->where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', $hoy)->count(),
            'cuentas_por_pagar_vencidas' => CuentaPorPagar::query()->tap($scope)->where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', $hoy)->count(),
            'caja_abierta' => $caja ? [
                'id' => $caja->id,
                'monto_apertura' => $caja->monto_apertura,
                'fecha_apertura' => $caja->fecha_apertura?->toDateTimeString(),
            ] : null,
        ];
    }
}