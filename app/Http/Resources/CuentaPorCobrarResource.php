<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CuentaPorCobrarResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'cliente_id' => $this->cliente_id,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'venta_id' => $this->venta_id,
            'venta' => new VentaResource($this->whenLoaded('venta')),
            'monto_total' => $this->monto_total,
            'monto_pagado' => $this->monto_pagado,
            'saldo' => $this->saldo,
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'pagos' => CuentaPorCobrarPagoResource::collection($this->whenLoaded('pagos')),
        ];
    }
}
