<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmpresaConfiguracionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social ?: $this->nombre,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion_fiscal' => $this->direccion_fiscal ?: $this->direccion,
            'ubigeo' => $this->ubigeo,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? url('/api/configuracion/empresa/logo') : null,
            'estado' => (bool) ($this->estado ?? $this->active),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}