<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PosProductoService
{
    public function buscar(array $context, ?string $query = null, int $limit = 20): Collection
    {
        $query = trim((string) $query);
        $today = Carbon::today();

        $productos = Producto::query()
            ->with([
                'categoria',
                'laboratorio',
                'principioActivo',
                'principiosActivos',
                'afectacionIgv',
                'presentaciones' => fn ($builder) => $builder->where('estado', true)->orderByDesc('es_principal')->orderBy('nombre'),
                'stocks' => fn ($builder) => $builder
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('empresa_id', $context['empresa_id'])
                    ->where('tienda_id', $context['tienda_id'])
                    ->where('estado', true)
                    ->where('cantidad_actual', '>', 0)
                    ->with('lote'),
                'lotes' => fn ($builder) => $builder
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('empresa_id', $context['empresa_id'])
                    ->where('estado', true)
                    ->with(['stocks' => fn ($stock) => $stock
                        ->where('tenant_id', $context['tenant_id'])
                        ->where('empresa_id', $context['empresa_id'])
                        ->where('tienda_id', $context['tienda_id'])
                        ->where('estado', true)
                        ->where('cantidad_actual', '>', 0),
                    ])
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('codigo_lote'),
            ])
            ->where('tenant_id', $context['tenant_id'])
            ->where('empresa_id', $context['empresa_id'])
            ->where('estado', true)
            ->whereHas('stocks', fn ($builder) => $builder
                ->where('tienda_id', $context['tienda_id'])
                ->where('estado', true)
                ->where('cantidad_actual', '>', 0)
            )
            ->when($query !== '', function ($builder) use ($query) {
                $like = '%'.$query.'%';
                $builder->where(function ($subquery) use ($like) {
                    $subquery->where('nombre', 'ilike', $like)
                        ->orWhere('codigo_interno', 'ilike', $like)
                        ->orWhereHas('presentaciones', fn ($presentacion) => $presentacion->where('codigo_barra', 'ilike', $like))
                        ->orWhereHas('principioActivo', fn ($principio) => $principio->where('nombre', 'ilike', $like))
                        ->orWhereHas('principiosActivos', fn ($principio) => $principio->where('nombre', 'ilike', $like))
                        ->orWhereHas('laboratorio', fn ($laboratorio) => $laboratorio->where('nombre', 'ilike', $like))
                        ->orWhereHas('categoria', fn ($categoria) => $categoria->where('nombre', 'ilike', $like));
                });
            })
            ->orderBy('nombre')
            ->limit($limit)
            ->get();

        return $productos
            ->map(function (Producto $producto) use ($query, $today) {
                $validStocks = $producto->stocks->filter(function ($stock) use ($producto, $today) {
                    if (! $producto->maneja_lote) {
                        return $stock->lote_id === null;
                    }

                    if (! $stock->lote || ! $stock->lote->estado) {
                        return false;
                    }

                    if ($producto->maneja_vencimiento && $stock->lote->fecha_vencimiento && $stock->lote->fecha_vencimiento->lt($today)) {
                        return false;
                    }

                    return true;
                });

                $stockBase = round($validStocks->sum(fn ($stock) => (float) $stock->cantidad_actual), 4);
                $exactBarcode = $producto->presentaciones->firstWhere('codigo_barra', $query)?->codigo_barra;

                $lotes = $producto->maneja_lote
                    ? $producto->lotes
                        ->map(function ($lote) {
                            $lote->setRelation('stock', $lote->stocks->first());
                            return $lote;
                        })
                        ->filter(fn ($lote) => $lote->stock && (float) $lote->stock->cantidad_actual > 0)
                        ->filter(fn ($lote) => ! $producto->maneja_vencimiento || ! $lote->fecha_vencimiento || $lote->fecha_vencimiento->gte($today))
                        ->values()
                    : collect();

                $producto->setAttribute('pos_stock_base', $stockBase);
                $producto->setAttribute('pos_lotes', $lotes);
                $producto->setAttribute('pos_exact_barcode', $exactBarcode);

                return $producto;
            })
            ->filter(fn (Producto $producto) => (float) $producto->getAttribute('pos_stock_base') > 0)
            ->sortByDesc(fn (Producto $producto) => $producto->presentaciones->contains('codigo_barra', $query))
            ->values();
    }

    public function rapidos(array $context, int $limit = 30): Collection
    {
        return $this->buscar($context, null, $limit);
    }
}
