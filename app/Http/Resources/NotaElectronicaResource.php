<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotaElectronicaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'comprobante_referencia_id' => $this->comprobante_referencia_id,
            'tipo_nota' => $this->tipo_nota,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'motivo_codigo' => $this->motivo_codigo,
            'motivo_descripcion' => $this->motivo_descripcion,
            'fecha_emision' => $this->fecha_emision?->toDateTimeString(),
            'moneda' => $this->moneda,
            'subtotal' => $this->subtotal,
            'total_igv' => $this->total_igv,
            'total' => $this->total,
            'estado' => $this->estado,
            'afecta_stock' => (bool) $this->afecta_stock,
            'afecta_caja' => (bool) $this->afecta_caja,
            'observacion' => $this->observacion,
            'detalles' => NotaDetalleResource::collection($this->whenLoaded('detalles')),
            'comprobante_electronico' => new ComprobanteElectronicoResource($this->whenLoaded('comprobanteElectronico')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
