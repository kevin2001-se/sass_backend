<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoteResource extends JsonResource
{
    public function toArray($request): array
    {
        $stock = $this->relationLoaded('stocks') ? $this->stocks->first() : null;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'producto_id' => $this->producto_id,
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'codigo_lote' => $this->codigo_lote,
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'estado' => $this->estado,
            'stock' => $stock ? new StockResource($stock) : null,
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
