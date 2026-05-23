<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ResumenDiarioDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'comprobante_electronico_id' => $this->comprobante_electronico_id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'estado_item' => $this->estado_item,
            'total' => (float) $this->total,
            'total_igv' => (float) $this->total_igv,
        ];
    }
}
