<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => new ProveedorResource($this->whenLoaded('proveedor')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tipo_comprobante' => $this->tipo_comprobante,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'tipo_compra' => $this->tipo_compra,
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'subtotal' => $this->subtotal,
            'total_igv' => $this->total_igv,
            'total_descuento' => $this->total_descuento,
            'total' => $this->total,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'detalles' => CompraDetalleResource::collection($this->whenLoaded('detalles')),
            'pagos' => CompraPagoResource::collection($this->whenLoaded('pagos')),
            'cuenta_por_pagar' => new CuentaPorPagarResource($this->whenLoaded('cuentaPorPagar')),
        ];
    }
}
