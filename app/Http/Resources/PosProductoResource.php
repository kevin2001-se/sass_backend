<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PosProductoResource extends JsonResource
{
    public function toArray($request): array
    {
        $stockBase = (float) $this->getAttribute('pos_stock_base');
        $exactBarcode = $this->getAttribute('pos_exact_barcode');
        $presentaciones = $this->presentaciones;

        if ($exactBarcode) {
            $presentaciones = $presentaciones
                ->sortByDesc(fn ($presentacion) => $presentacion->codigo_barra === $exactBarcode)
                ->values();
        }

        $lotes = $this->getAttribute('pos_lotes') ?? collect();

        return [
            'id' => $this->id,
            'codigo_interno' => $this->codigo_interno,
            'nombre' => $this->nombre,
            'concentracion' => $this->concentracion,
            'requiere_receta' => $this->requiere_receta,
            'maneja_lote' => $this->maneja_lote,
            'maneja_vencimiento' => $this->maneja_vencimiento,
            'afecto_igv' => $this->afecto_igv,
            'categoria' => new CatalogoResource($this->whenLoaded('categoria')),
            'laboratorio' => new CatalogoResource($this->whenLoaded('laboratorio')),
            'principio_activo' => new CatalogoResource($this->whenLoaded('principioActivo')),
            'presentaciones' => $presentaciones->map(fn ($presentacion) => [
                'id' => $presentacion->id,
                'nombre' => $presentacion->nombre,
                'codigo_barra' => $presentacion->codigo_barra,
                'factor_conversion' => (float) $presentacion->factor_conversion,
                'precio_venta' => (float) $presentacion->precio_venta,
                'stock_disponible_base' => $stockBase,
                'stock_disponible_presentacion' => round($stockBase / max((float) $presentacion->factor_conversion, 0.0001), 4),
                'estado' => (bool) $presentacion->estado,
            ])->values(),
            'lotes' => $lotes->map(fn ($lote, $index) => [
                'id' => $lote->id,
                'codigo_lote' => $lote->codigo_lote,
                'fecha_vencimiento' => $lote->fecha_vencimiento?->toDateString(),
                'stock_disponible_base' => round((float) $lote->stock?->cantidad_actual, 4),
                'sugerido_fefo' => $index === 0,
                'estado' => (bool) $lote->estado,
            ])->values(),
        ];
    }
}

