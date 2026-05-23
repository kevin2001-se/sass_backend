<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventarioMovimientoResource extends JsonResource
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
            'producto_presentacion_id' => $this->producto_presentacion_id,
            'presentacion' => new ProductoPresentacionResource($this->whenLoaded('presentacion')),
            'lote_id' => $this->lote_id,
            'lote' => new LoteResource($this->whenLoaded('lote')),
            'tipo_movimiento' => $this->tipo_movimiento,
            'motivo' => $this->motivo,
            'cantidad_presentacion' => $this->cantidad_presentacion,
            'factor_conversion' => $this->factor_conversion,
            'cantidad_base' => $this->cantidad_base,
            'stock_anterior' => $this->stock_anterior,
            'stock_nuevo' => $this->stock_nuevo,
            'referencia_tipo' => $this->referencia_tipo,
            'referencia_id' => $this->referencia_id,
            'observacion' => $this->observacion,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
