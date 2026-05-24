<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotaDebitoDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nota_debito_id' => $this->nota_debito_id,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'subtotal' => $this->subtotal,
            'igv' => $this->igv,
            'total' => $this->total,
        ];
    }
}