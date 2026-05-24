<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tipo_comprobante' => $this->tipo_comprobante,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'tipo_venta' => $this->tipo_venta,
            'fecha_emision' => $this->fecha_emision?->toDateTimeString(),
            'subtotal' => $this->subtotal,
            'total_igv' => $this->total_igv,
            'total_descuento' => $this->total_descuento,
            'total' => $this->total,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'motivo_anulacion' => $this->motivo_anulacion,
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'anulado_by' => $this->anulado_by,
            'detalles' => VentaDetalleResource::collection($this->whenLoaded('detalles')),
            'pagos' => VentaPagoResource::collection($this->whenLoaded('pagos')),
            'metodos_pago' => $this->whenLoaded('pagos', fn () => $this->pagos->pluck('metodo_pago')->unique()->values()),
            'comprobante_electronico' => new ComprobanteElectronicoResource($this->whenLoaded('comprobanteElectronico')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
