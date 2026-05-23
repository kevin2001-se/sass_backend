<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoElectronicoDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'producto_id' => $this['producto_id'] ?? null,
            'descripcion' => $this['descripcion'] ?? null,
            'cantidad' => (float) ($this['cantidad'] ?? 0),
            'unidad_medida' => $this['unidad_medida'] ?? 'NIU',
            'precio_unitario' => isset($this['precio_unitario']) ? (float) $this['precio_unitario'] : null,
            'descuento' => isset($this['descuento']) ? (float) $this['descuento'] : null,
            'subtotal' => isset($this['subtotal']) ? (float) $this['subtotal'] : null,
            'igv' => isset($this['igv']) ? (float) $this['igv'] : null,
            'total' => isset($this['total']) ? (float) $this['total'] : null,
        ];
    }
}
