<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComunicacionBajaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'fecha_baja' => $this->fecha_baja?->toDateString(),
            'identificador' => $this->identificador,
            'correlativo' => $this->correlativo,
            'estado' => $this->estado,
            'estado_sunat' => $this->estado_sunat,
            'ticket_sunat' => $this->ticket_sunat ?: $this->ticket,
            'hash' => $this->hash,
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'intentos_envio' => $this->intentos_envio ?? 0,
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'consultado_at' => $this->consultado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'total_documentos' => $this->total_documentos,
            'observacion' => $this->observacion,
            'motivo_anulacion' => $this->motivo_anulacion,
            'tienda' => new TiendaResource($this->whenLoaded('tienda')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'anulado_by' => $this->anulado_by,
            'creado_por' => $this->whenLoaded('creadoPor', fn () => [
                'id' => $this->creadoPor?->id,
                'name' => $this->creadoPor?->name,
            ]),
            'anulado_por' => $this->whenLoaded('anuladoPor', fn () => [
                'id' => $this->anuladoPor?->id,
                'name' => $this->anuladoPor?->name,
            ]),
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'detalles_count' => $this->whenCounted('detalles'),
            'detalles' => ComunicacionBajaDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
