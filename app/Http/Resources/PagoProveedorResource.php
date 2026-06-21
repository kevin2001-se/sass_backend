<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PagoProveedorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'cuenta_por_pagar_id' => $this->cuenta_por_pagar_id,
            'cuenta_por_pagar' => new CuentaPorPagarResource($this->whenLoaded('cuentaPorPagar')),
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => new ProveedorResource($this->whenLoaded('proveedor')),
            'caja_id' => $this->caja_id,
            'caja' => new CajaResource($this->whenLoaded('caja')),
            'metodo_pago' => $this->metodo_pago,
            'monto' => $this->monto,
            'referencia' => $this->referencia,
            'fecha_pago' => $this->fecha_pago?->toDateString(),
            'observacion' => $this->observacion,
            'estado' => $this->estado,
            'created_by' => $this->created_by,
            'creado_por' => new UserResource($this->whenLoaded('creadoPor')),
            'anulado_by' => $this->anulado_by,
            'anulado_por' => new UserResource($this->whenLoaded('anuladoPor')),
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}