<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DistritoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'provincia_id' => $this->provincia_id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'ubigeo' => $this->ubigeo,
            'estado' => $this->estado,
            'provincia' => $this->whenLoaded('provincia', fn () => [
                'id' => $this->provincia->id,
                'codigo' => $this->provincia->codigo,
                'nombre' => $this->provincia->nombre,
                'departamento' => $this->provincia->relationLoaded('departamento') ? [
                    'id' => $this->provincia->departamento->id,
                    'codigo' => $this->provincia->departamento->codigo,
                    'nombre' => $this->provincia->departamento->nombre,
                ] : null,
            ]),
        ];
    }
}
