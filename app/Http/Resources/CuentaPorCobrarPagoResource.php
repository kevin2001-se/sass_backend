<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CuentaPorCobrarPagoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'cuenta_por_cobrar_id' => $this->cuenta_por_cobrar_id,
            'caja_id' => $this->caja_id,
            'user_id' => $this->user_id,
            'metodo_pago' => $this->metodo_pago,
            'monto' => $this->monto,
            'fecha_pago' => $this->fecha_pago?->toDateString(),
            'referencia' => $this->referencia,
            'observacion' => $this->observacion,
            'estado' => $this->estado,
        ];
    }
}
