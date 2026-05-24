<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MotivoNotaCreditoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
        ];
    }
}
