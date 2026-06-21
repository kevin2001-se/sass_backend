<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ResumenDiarioDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'documento_id' => $this->documento_id ?: $this->comprobante_electronico_id,
            'comprobante_electronico_id' => $this->comprobante_electronico_id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'numero_completo' => $this->numero_completo ?: $this->numero_comprobante,
            'cliente_tipo_documento' => $this->cliente_tipo_documento,
            'cliente_numero_documento' => $this->cliente_numero_documento,
            'cliente_nombre' => $this->cliente_nombre,
            'subtotal' => (float) ($this->subtotal ?? 0),
            'total_igv' => (float) $this->total_igv,
            'total' => (float) $this->total,
            'estado_documento' => $this->estado_documento,
            'estado_item' => $this->estado_item,
            'accion' => $this->accion ?: 'ALTA',
            'estado_baja' => $this->estado_baja,
            'motivo_baja' => $this->motivo_baja,
        ];
    }
}
