<?php

namespace App\Services\Reportes;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Services\Reportes\Concerns\AppliesReportFilters;
use Illuminate\Support\Facades\DB;

class ReporteCajaService
{
    use AppliesReportFilters;

    public function resumenCaja(array $filtros): array
    {
        $query = CajaMovimiento::query();
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'created_at');

        $ingresos = (clone $query)->whereIn('tipo_movimiento', [CajaMovimiento::APERTURA, CajaMovimiento::INGRESO, CajaMovimiento::VENTA, CajaMovimiento::AJUSTE])->sum('monto');
        $egresos = (clone $query)->whereIn('tipo_movimiento', [CajaMovimiento::EGRESO, CajaMovimiento::ANULACION_VENTA])->sum('monto');

        return ['ingresos' => round((float) $ingresos, 2), 'egresos' => round((float) $egresos, 2), 'saldo' => round((float) $ingresos - (float) $egresos, 2)];
    }

    public function ventasPorMetodoPago(array $filtros): array
    {
        $query = CajaMovimiento::query();
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'created_at');

        $metodos = $query->where('tipo_movimiento', CajaMovimiento::VENTA)
            ->select('metodo_pago', DB::raw('SUM(monto) as total'))
            ->groupBy('metodo_pago')
            ->orderByDesc('total')
            ->get();

        return ['total' => round((float) $metodos->sum('total'), 2), 'metodos' => $metodos];
    }

    public function historialCierres(array $filtros)
    {
        $query = Caja::with(['userApertura', 'userCierre'])->where('estado', Caja::CERRADA)->orderByDesc('fecha_cierre');
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'fecha_cierre');

        return $query->paginate($filtros['per_page'] ?? 15);
    }
}
