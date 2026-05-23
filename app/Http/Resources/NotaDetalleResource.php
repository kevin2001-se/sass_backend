<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotaDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'venta_detalle_id' => $this->venta_detalle_id,
            'producto_id' => $this->producto_id,
            'producto_presentacion_id' => $this->producto_presentacion_id,
            'lote_id' => $this->lote_id,
            'descripcion' => $this->descripcion,
            'cantidad_presentacion' => $this->cantidad_presentacion,
            'factor_conversion' => $this->factor_conversion,
            'cantidad_base' => $this->cantidad_base,
            'precio_unitario' => $this->precio_unitario,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
        ];
    }
}
