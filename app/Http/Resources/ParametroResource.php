<?php

namespace App\Http\Resources;

use App\Services\ParametroService;
use Illuminate\Http\Resources\Json\JsonResource;

class ParametroResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'clave' => $this->clave,
            'valor' => app(ParametroService::class)->castValue($this->valor, $this->tipo),
            'tipo' => $this->tipo,
            'grupo' => $this->grupo,
            'descripcion' => $this->descripcion,
            'estado' => (bool) $this->estado,
        ];
    }
}