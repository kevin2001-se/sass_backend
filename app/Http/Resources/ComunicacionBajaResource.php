<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ComunicacionBajaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'fecha_baja' => $this->fecha_baja?->toDateString(),
            'fecha_envio' => $this->fecha_envio?->toDateString(),
            'correlativo' => $this->correlativo,
            'identificador' => $this->identificador,
            'estado_sunat' => $this->estado_sunat,
            'ticket' => $this->ticket,
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'intentos_envio' => $this->intentos_envio,
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'observacion' => $this->observacion,
            'total_documentos' => $this->whenCounted('detalles'),
            'detalles' => ComunicacionBajaDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
