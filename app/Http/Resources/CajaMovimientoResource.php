<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CajaMovimientoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'caja_id' => $this->caja_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tipo_movimiento' => $this->tipo_movimiento,
            'metodo_pago' => $this->metodo_pago,
            'concepto' => $this->concepto,
            'monto' => $this->monto,
            'referencia_tipo' => $this->referencia_tipo,
            'referencia_id' => $this->referencia_id,
            'observacion' => $this->observacion,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
