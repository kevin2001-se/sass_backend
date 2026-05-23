<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VentaDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'producto_id' => $this->producto_id,
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'producto_presentacion_id' => $this->producto_presentacion_id,
            'presentacion' => new ProductoPresentacionResource($this->whenLoaded('presentacion')),
            'lote_id' => $this->lote_id,
            'lote' => new LoteResource($this->whenLoaded('lote')),
            'descripcion' => $this->descripcion,
            'cantidad_presentacion' => $this->cantidad_presentacion,
            'factor_conversion' => $this->factor_conversion,
            'cantidad_base' => $this->cantidad_base,
            'precio_unitario' => $this->precio_unitario,
            'descuento' => $this->descuento,
            'afecto_igv' => $this->afecto_igv,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
        ];
    }
}
