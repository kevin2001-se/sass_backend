<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotaCreditoDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nota_credito_id' => $this->nota_credito_id,
            'venta_detalle_id' => $this->venta_detalle_id,
            'producto_id' => $this->producto_id,
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'descripcion' => $this->descripcion,
            'unidad_medida' => $this->unidad_medida,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'descuento' => $this->descuento,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
        ];
    }
}
