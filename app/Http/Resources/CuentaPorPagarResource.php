<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CuentaPorPagarResource extends JsonResource
{
    public function toArray($request): array
    {
        $saldo = $this->saldo;
        $estado = $this->estado;
        if ($estado === 'PENDIENTE' && $this->fecha_vencimiento && $this->fecha_vencimiento->lt(now()->startOfDay()) && (float) $saldo > 0) {
            $estado = 'VENCIDO';
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => new ProveedorResource($this->whenLoaded('proveedor')),
            'compra_id' => $this->compra_id,
            'compra' => new CompraResource($this->whenLoaded('compra')),
            'tienda' => new TiendaResource($this->whenLoaded('tienda')),
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'monto_total' => $this->monto_total,
            'monto_pagado' => $this->monto_pagado,
            'saldo' => $saldo,
            'saldo_pendiente' => $saldo,
            'estado' => $estado,
            'estado_registrado' => $this->estado,
            'observacion' => $this->observacion,
            'pagos' => PagoProveedorResource::collection($this->whenLoaded('pagos')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}