<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComunicacionBajaDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comunicacion_baja_id' => $this->comunicacion_baja_id,
            'comprobante_id' => $this->comprobante_id ?: $this->comprobante_electronico_id,
            'comprobante_electronico_id' => $this->comprobante_electronico_id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'numero_completo' => $this->numero_completo ?: $this->numero_comprobante,
            'motivo_baja' => $this->motivo_baja,
            'comprobante' => new ComprobanteElectronicoResource($this->whenLoaded('comprobante')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
