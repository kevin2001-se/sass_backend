<?php

namespace App\Services\Reportes;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\Reportes\Concerns\AppliesReportFilters;
use Illuminate\Support\Facades\DB;

class ReporteVentasService
{
    use AppliesReportFilters;

    public function resumenVentas(array $filtros): array
    {
        $query = Venta::query();
        $this->aplicarFiltrosVenta($query, $filtros);

        return [
            'cantidad_ventas' => (clone $query)->count(),
            'total_vendido' => round((float) (clone $query)->sum('total'), 2),
            'total_igv' => round((float) (clone $query)->sum('total_igv'), 2),
            'total_descuento' => round((float) (clone $query)->sum('total_descuento'), 2),
        ];
    }

    public function ventasPorMetodoPago(array $filtros): array
    {
        $query = VentaPago::query()
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->where('ventas.tenant_id', $filtros['tenant_id'])
            ->where('ventas.empresa_id', $filtros['empresa_id'])
            ->where('ventas.tienda_id', $filtros['tienda_id'])
            ->where('ventas.estado', Venta::REGISTRADA);

        $this->rangoFechas($query, $filtros, 'ventas.fecha_emision');

        $metodos = $query->select('venta_pagos.metodo_pago', DB::raw('SUM(venta_pagos.monto) as total'))
            ->groupBy('venta_pagos.metodo_pago')
            ->orderByDesc('total')
            ->get();

        return ['total' => round((float) $metodos->sum('total'), 2), 'metodos' => $metodos];
    }

    public function productosMasVendidos(array $filtros): array
    {
        $query = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
            ->where('ventas.tenant_id', $filtros['tenant_id'])
            ->where('ventas.empresa_id', $filtros['empresa_id'])
            ->where('ventas.tienda_id', $filtros['tienda_id'])
            ->where('ventas.estado', Venta::REGISTRADA);

        $this->rangoFechas($query, $filtros, 'ventas.fecha_emision');

        return ['productos' => $query->select('productos.id as producto_id', 'productos.nombre as producto', DB::raw('SUM(venta_detalles.cantidad_base) as cantidad_base'), DB::raw('SUM(venta_detalles.total) as total_vendido'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad_base')
            ->limit(20)
            ->get()];
    }

    public function ventasDetalle(array $filtros)
    {
        $query = Venta::with(['cliente', 'user', 'pagos'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        $this->aplicarFiltrosVenta($query, $filtros);

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    protected function aplicarFiltrosVenta($query, array $filtros): void
    {
        $this->scopeBase($query, $filtros);
        $this->rangoFechas($query, $filtros, 'fecha_emision');
        $query
            ->when($filtros['usuario_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['tipo_comprobante'] ?? null, fn ($q, $tipo) => $q->where('tipo_comprobante', $tipo));
    }
}
