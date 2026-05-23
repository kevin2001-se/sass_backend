<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GuiaRemisionDocumentoRelacionadoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'comprobante_electronico_id' => $this->comprobante_electronico_id,
            'venta_id' => $this->venta_id,
            'compra_id' => $this->compra_id,
        ];
    }
}
