<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AfectacionIgvResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'abreviatura' => $this->abreviatura,
            'aplica_igv' => (bool) $this->aplica_igv,
            'es_gratuito' => (bool) $this->es_gratuito,
            'estado' => (bool) $this->estado,
        ];
    }
}
