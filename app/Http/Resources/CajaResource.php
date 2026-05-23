<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CajaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'user_apertura_id' => $this->user_apertura_id,
            'user_apertura' => new UserResource($this->whenLoaded('userApertura')),
            'user_cierre_id' => $this->user_cierre_id,
            'user_cierre' => new UserResource($this->whenLoaded('userCierre')),
            'fecha_apertura' => $this->fecha_apertura?->toDateTimeString(),
            'fecha_cierre' => $this->fecha_cierre?->toDateTimeString(),
            'monto_apertura' => $this->monto_apertura,
            'monto_cierre_sistema' => $this->monto_cierre_sistema,
            'monto_cierre_real' => $this->monto_cierre_real,
            'diferencia' => $this->diferencia,
            'estado' => $this->estado,
            'observacion_apertura' => $this->observacion_apertura,
            'observacion_cierre' => $this->observacion_cierre,
            'movimientos' => CajaMovimientoResource::collection($this->whenLoaded('movimientos')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
