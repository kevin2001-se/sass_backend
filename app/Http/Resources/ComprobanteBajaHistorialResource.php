<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteBajaHistorialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'comprobante_id' => $this->comprobante_id,
            'estado_anterior' => $this->estado_anterior,
            'estado_nuevo' => $this->estado_nuevo,
            'motivo' => $this->motivo,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id' => $this->usuario->id,
                'name' => $this->usuario->name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}