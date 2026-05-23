<?php

namespace App\Services\Reportes;

use App\Models\InventarioMovimiento;
use App\Models\Lote;
use App\Models\Stock;
use App\Services\Reportes\Concerns\AppliesReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReporteInventarioService
{
    use AppliesReportFilters;

    public function stockActual(array $filtros)
    {
        return $this->stockQuery($filtros)->paginate($filtros['per_page'] ?? 15);
    }

    public function stockValorizado(array $filtros): array
    {
        $items = $this->stockQuery($filtros)
            ->join('producto_presentaciones', function ($join) {
                $join->on('producto_presentaciones.producto_id', '=', 'stocks.producto_id')
                    ->where('producto_presentaciones.es_principal', true);
            })
            ->select('stocks.producto_id', 'productos.nombre as producto', DB::raw('SUM(stocks.cantidad_actual) as cantidad_base'), DB::raw('MAX(producto_presentaciones.precio_venta) as precio_referencia'), DB::raw('SUM(stocks.cantidad_actual * producto_presentaciones.precio_venta) as valor_estimado'))
            ->groupBy('stocks.producto_id', 'productos.nombre')
            ->get();

        return ['total_valorizado' => round((float) $items->sum('valor_estimado'), 2), 'items' => $items];
    }

    public function productosBajoStock(array $filtros)
    {
        return $this->stockQuery($filtros)->whereNotNull('cantidad_minima')->whereColumn('cantidad_actual', '<=', 'cantidad_minima')->paginate($filtros['per_page'] ?? 15);
    }

    public function lotesPorVencer(array $filtros)
    {
        return $this->lotesQuery($filtros)->whereBetween('fecha_vencimiento', [today(), today()->copy()->addDays(30)])->paginate($filtros['per_page'] ?? 15);
    }

    public function lotesVencidos(array $filtros)
    {
        return $this->lotesQuery($filtros)->whereDate('fecha_vencimiento', '<', today())->paginate($filtros['per_page'] ?? 15);
    }

    public function kardexProducto(array $filtros)
    {
        $query = InventarioMovimiento::with(['producto', 'presentacion', 'lote', 'user'])->orderByDesc('created_at')->orderByDesc('id');
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'created_at');
        $query->when($filtros['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id));

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    protected function stockQuery(array $filtros)
    {
        $query = Stock::with(['producto', 'lote'])->join('productos', 'productos.id', '=', 'stocks.producto_id')->select('stocks.*');
        $query->where('stocks.tenant_id', $filtros['tenant_id'])
            ->where('stocks.empresa_id', $filtros['empresa_id']);

        if (! empty($filtros['tienda_id'])) {
            $query->where('stocks.tienda_id', $filtros['tienda_id']);
        }

        $query->when($filtros['producto_id'] ?? null, fn ($q, $id) => $q->where('stocks.producto_id', $id));

        return $query;
    }

    protected function lotesQuery(array $filtros)
    {
        $query = Lote::with([
            'producto',
            'stocks' => fn ($stockQuery) => $this->scopeStockForLote($stockQuery, $filtros)->where('cantidad_actual', '>', 0),
        ])->whereNotNull('fecha_vencimiento')->orderBy('fecha_vencimiento');

        $this->scopeBase($query, $filtros, false);
        $query->whereHas('stocks', fn ($stockQuery) => $this->scopeStockForLote($stockQuery, $filtros)->where('cantidad_actual', '>', 0));
        $query->when($filtros['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id));

        return $query;
    }

    protected function scopeStockForLote(Builder $query, array $filtros): Builder
    {
        $query->where('tenant_id', $filtros['tenant_id'])
            ->where('empresa_id', $filtros['empresa_id']);

        if (! empty($filtros['tienda_id'])) {
            $query->where('tienda_id', $filtros['tienda_id']);
        }

        return $query;
    }
}