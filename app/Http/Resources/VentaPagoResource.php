<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VentaPagoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'metodo_pago' => $this->metodo_pago,
            'monto' => $this->monto,
            'referencia' => $this->referencia,
            'estado' => $this->estado,
        ];
    }
}
