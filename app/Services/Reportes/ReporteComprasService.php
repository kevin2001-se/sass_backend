<?php

namespace App\Services\Reportes;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Services\Reportes\Concerns\AppliesReportFilters;
use Illuminate\Support\Facades\DB;

class ReporteComprasService
{
    use AppliesReportFilters;

    public function resumenCompras(array $filtros): array
    {
        $query = Compra::query();
        $this->aplicarFiltrosCompra($query, $filtros);

        return [
            'cantidad_compras' => (clone $query)->count(),
            'total_comprado' => round((float) (clone $query)->sum('total'), 2),
            'total_igv' => round((float) (clone $query)->sum('total_igv'), 2),
            'total_descuento' => round((float) (clone $query)->sum('total_descuento'), 2),
        ];
    }

    public function productosMasComprados(array $filtros): array
    {
        $query = CompraDetalle::query()
            ->join('compras', 'compras.id', '=', 'compra_detalles.compra_id')
            ->join('productos', 'productos.id', '=', 'compra_detalles.producto_id')
            ->where('compras.tenant_id', $filtros['tenant_id'])
            ->where('compras.empresa_id', $filtros['empresa_id'])
            ->where('compras.tienda_id', $filtros['tienda_id'])
            ->where('compras.estado', Compra::REGISTRADA);

        $this->rangoFechas($query, $filtros, 'compras.fecha_emision');

        return ['productos' => $query->select('productos.id as producto_id', 'productos.nombre as producto', DB::raw('SUM(compra_detalles.cantidad_base) as cantidad_base'), DB::raw('SUM(compra_detalles.total) as total_comprado'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad_base')
            ->limit(20)
            ->get()];
    }

    public function comprasDetalle(array $filtros)
    {
        $query = Compra::with(['proveedor', 'user'])->orderByDesc('fecha_emision')->orderByDesc('id');
        $this->aplicarFiltrosCompra($query, $filtros);

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    protected function aplicarFiltrosCompra($query, array $filtros): void
    {
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'fecha_emision');
        $query
            ->when($filtros['usuario_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $id) => $q->where('proveedor_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado));
    }
}
