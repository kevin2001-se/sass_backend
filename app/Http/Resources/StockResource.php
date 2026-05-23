<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'producto_id' => $this->producto_id,
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'lote_id' => $this->lote_id,
            'lote' => new LoteResource($this->whenLoaded('lote')),
            'cantidad_actual' => $this->cantidad_actual,
            'cantidad_minima' => $this->cantidad_minima,
            'cantidad_maxima' => $this->cantidad_maxima,
            'estado' => $this->estado,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
