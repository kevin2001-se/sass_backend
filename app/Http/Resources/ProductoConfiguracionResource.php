<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductoConfiguracionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'autogenerar_codigo_interno' => (bool) $this->autogenerar_codigo_interno,
            'prefijo_codigo_interno' => $this->prefijo_codigo_interno,
            'ultimo_correlativo_codigo_interno' => (int) $this->ultimo_correlativo_codigo_interno,
            'autogenerar_codigo_barra' => (bool) $this->autogenerar_codigo_barra,
            'prefijo_codigo_barra' => $this->prefijo_codigo_barra,
            'ultimo_correlativo_codigo_barra' => (int) $this->ultimo_correlativo_codigo_barra,
            'estado' => (bool) $this->estado,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
