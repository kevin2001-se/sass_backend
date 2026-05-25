<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SerieComprobanteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'tipo_comprobante' => $this->tipo_comprobante,
            'tienda' => $this->whenLoaded('tienda', fn () => [
                'id' => $this->tienda?->id,
                'nombre' => $this->tienda?->nombre,
                'codigo' => $this->tienda?->codigo,
            ]),
            'serie' => $this->serie,
            'correlativo_actual' => $this->correlativo_actual,
            'estado' => $this->estado,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
