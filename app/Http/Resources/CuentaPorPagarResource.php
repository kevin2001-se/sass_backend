<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CuentaPorPagarResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => new ProveedorResource($this->whenLoaded('proveedor')),
            'compra_id' => $this->compra_id,
            'compra' => new CompraResource($this->whenLoaded('compra')),
            'monto_total' => $this->monto_total,
            'monto_pagado' => $this->monto_pagado,
            'saldo' => $this->saldo,
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'estado' => $this->estado,
            'pagos' => CuentaPorPagarPagoResource::collection($this->whenLoaded('pagos')),
        ];
    }
}
