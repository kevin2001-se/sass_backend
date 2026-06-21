<?php

namespace App\Services\Reportes;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ReporteExportService
{
    public function __construct(
        protected ReporteVentasService $ventas,
        protected ReporteComprasService $compras,
        protected ReporteInventarioService $inventario,
        protected ReporteCajaService $caja,
        protected ReporteFinancieroService $financiero,
    ) {
    }

    public function descargarExcel(string $grupo, string $reporte, array $filtros): Response
    {
        $data = $this->resolver($grupo, $reporte, $filtros);
        $html = view('reportes.export-table', $data)->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$data['filename'].'.xls"',
        ]);
    }

    public function descargarPdf(string $grupo, string $reporte, array $filtros): Response
    {
        $data = $this->resolver($grupo, $reporte, $filtros);
        $pdf = Pdf::loadView('reportes.export-pdf', $data)->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$data['filename'].'.pdf"',
        ]);
    }

    public function resolver(string $grupo, string $reporte, array $filtros): array
    {
        $filtros['per_page'] = min((int) ($filtros['per_page'] ?? 100), 100);

        return match ($grupo.'/'.$reporte) {
            'ventas/resumen' => $this->fromAssoc('Reporte de ventas', $this->ventas->resumenVentas($filtros)),
            'ventas/metodos-pago' => $this->fromRows('Ventas por metodo de pago', $this->ventas->ventasPorMetodoPago($filtros)['metodos']),
            'ventas/productos-mas-vendidos' => $this->fromRows('Productos mas vendidos', $this->ventas->productosMasVendidos($filtros)['productos']),
            'ventas/detalle' => $this->fromRows('Detalle de ventas', $this->ventas->ventasDetalle($filtros)),

            'compras/resumen' => $this->fromAssoc('Reporte de compras', $this->compras->resumenCompras($filtros)),
            'compras/productos-mas-comprados' => $this->fromRows('Productos mas comprados', $this->compras->productosMasComprados($filtros)['productos']),
            'compras/detalle' => $this->fromRows('Detalle de compras', $this->compras->comprasDetalle($filtros)),

            'inventario/stock-actual' => $this->fromRows('Stock actual', $this->inventario->stockActual($filtros)),
            'inventario/stock-valorizado' => $this->fromRows('Stock valorizado', $this->inventario->stockValorizado($filtros)['items'], ['total_valorizado' => $this->inventario->stockValorizado($filtros)['total_valorizado'] ?? 0]),
            'inventario/bajo-stock' => $this->fromRows('Productos bajo stock', $this->inventario->productosBajoStock($filtros)),
            'inventario/lotes-por-vencer' => $this->fromRows('Lotes por vencer', $this->inventario->lotesPorVencer($filtros)),
            'inventario/lotes-vencidos' => $this->fromRows('Lotes vencidos', $this->inventario->lotesVencidos($filtros)),
            'inventario/kardex' => $this->fromRows('Kardex de inventario', $this->inventario->kardexProducto($filtros)),

            'caja/resumen' => $this->fromAssoc('Reporte de caja', $this->caja->resumenCaja($filtros)),
            'caja/metodos-pago' => $this->fromRows('Caja por metodo de pago', $this->caja->ventasPorMetodoPago($filtros)['metodos']),
            'caja/cierres' => $this->fromRows('Cierres de caja', $this->caja->historialCierres($filtros)),

            'financiero/flujo' => $this->fromAssoc('Flujo financiero', $this->financiero->flujoIngresosEgresos($filtros)),
            'financiero/cuentas-por-cobrar' => $this->fromRows('Cuentas por cobrar', $this->financiero->cuentasPorCobrar($filtros)),
            'financiero/cuentas-por-pagar' => $this->fromRows('Cuentas por pagar', $this->financiero->cuentasPorPagar($filtros)),
            default => throw new InvalidArgumentException('Reporte no soportado para exportacion.'),
        };
    }

    protected function fromAssoc(string $title, array $items, array $summary = []): array
    {
        $rows = [collect($items)->map(fn ($value) => $this->stringValue($value))->all()];

        return $this->payload($title, array_keys($items), $rows, $summary);
    }

    protected function fromRows(string $title, mixed $items, array $summary = []): array
    {
        if ($items instanceof LengthAwarePaginator) {
            $items = $items->getCollection();
        }

        $rows = collect($items)->map(fn ($item) => $this->flatten($item))->values();
        $headers = $rows->flatMap(fn ($row) => array_keys($row))->unique()->values()->all();
        $values = $rows->map(fn ($row) => collect($headers)->map(fn ($header) => $row[$header] ?? '')->all())->all();

        return $this->payload($title, $headers, $values, $summary);
    }

    protected function payload(string $title, array $headers, array $rows, array $summary = []): array
    {
        return [
            'title' => $title,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filename' => str($title)->ascii()->slug('-').'-'.now()->format('Ymd-His'),
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    protected function flatten(mixed $item): array
    {
        $array = is_array($item) ? $item : $item->toArray();
        $flattened = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flattened[$key] = $this->stringValue($value);
                continue;
            }

            $flattened[$key] = $this->stringValue($value);
        }

        return $flattened;
    }

    protected function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'SI' : 'NO';
        }

        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => ! is_array($item))->implode(' | ');
        }

        return (string) ($value ?? '');
    }
}
