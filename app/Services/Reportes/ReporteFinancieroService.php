<?php

namespace App\Services\Reportes;

use App\Models\CajaMovimiento;
use App\Models\CuentaPorCobrar;
use App\Models\CuentaPorPagar;
use App\Services\Reportes\Concerns\AppliesReportFilters;

class ReporteFinancieroService
{
    use AppliesReportFilters;

    public function cuentasPorCobrar(array $filtros)
    {
        $query = CuentaPorCobrar::with(['cliente', 'venta'])->orderBy('fecha_vencimiento');
        $this->scopeBase($query, $filtros);
        $query->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado));

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function cuentasPorPagar(array $filtros)
    {
        $query = CuentaPorPagar::with(['proveedor', 'compra'])->orderBy('fecha_vencimiento');
        $this->scopeBase($query, $filtros);
        $query->when($filtros['proveedor_id'] ?? null, fn ($q, $id) => $q->where('proveedor_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado));

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function flujoIngresosEgresos(array $filtros): array
    {
        $query = CajaMovimiento::query();
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'created_at');

        $ingresos = (clone $query)->whereIn('tipo_movimiento', ['APERTURA', 'INGRESO', 'VENTA', 'AJUSTE'])->sum('monto');
        $egresos = (clone $query)->whereIn('tipo_movimiento', ['EGRESO', 'ANULACION_VENTA'])->sum('monto');

        return [
            'ingresos' => round((float) $ingresos, 2),
            'egresos' => round((float) $egresos, 2),
            'saldo' => round((float) $ingresos - (float) $egresos, 2),
            'cuentas_por_cobrar_pendiente' => round((float) CuentaPorCobrar::query()->tap(fn ($q) => $this->scopeBase($q, $filtros))->whereNotIn('estado', ['PAGADA', 'ANULADA'])->sum('saldo'), 2),
            'cuentas_por_pagar_pendiente' => round((float) CuentaPorPagar::query()->tap(fn ($q) => $this->scopeBase($q, $filtros))->whereNotIn('estado', ['PAGADA', 'ANULADA'])->sum('saldo'), 2),
        ];
    }
}
