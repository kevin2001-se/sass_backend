<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GuiaRemisionDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'guia_remision_id' => $this->guia_remision_id,
            'producto_id' => $this->producto_id,
            'producto' => $this->whenLoaded('producto', fn () => [
                'id' => $this->producto?->id,
                'codigo_interno' => $this->producto?->codigo_interno,
                'nombre' => $this->producto?->nombre,
            ]),
            'descripcion' => $this->descripcion,
            'unidad_medida' => $this->unidad_medida,
            'cantidad' => (float) $this->cantidad,
            'peso' => $this->peso !== null ? (float) $this->peso : null,
            'codigo_producto' => $this->codigo_producto,
        ];
    }
}