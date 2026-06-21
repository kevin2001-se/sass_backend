<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ResumenDiarioResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'fecha_resumen' => $this->fecha_resumen?->toDateString(),
            'fecha_envio' => $this->fecha_envio?->toDateString(),
            'correlativo' => $this->correlativo,
            'identificador' => $this->identificador,
            'estado' => $this->estado ?: 'REGISTRADO',
            'estado_sunat' => $this->estado_sunat ?: 'PENDIENTE',
            'total_documentos' => (int) ($this->total_documentos ?? $this->detalles_count ?? 0),
            'total_boletas' => (int) ($this->total_boletas ?? 0),
            'total_notas_credito' => (int) ($this->total_notas_credito ?? 0),
            'total_notas_debito' => (int) ($this->total_notas_debito ?? 0),
            'monto_total' => (float) ($this->monto_total ?? 0),
            'ticket' => $this->ticket_sunat ?: $this->ticket,
            'ticket_sunat' => $this->ticket_sunat ?: $this->ticket,
            'hash' => $this->hash,
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'intentos_envio' => (int) ($this->intentos_envio ?? 0),
            'tiene_pdf_a4' => filled($this->pdf_a4_path),
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'pdf_generado_at' => $this->pdf_generado_at?->toDateTimeString(),
            'consultado_at' => $this->consultado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'observacion' => $this->observacion,
            'motivo_anulacion' => $this->motivo_anulacion,
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'tienda' => $this->whenLoaded('tienda', fn () => [
                'id' => $this->tienda?->id,
                'nombre' => $this->tienda?->nombre,
            ]),
            'usuario' => $this->whenLoaded('creadoPor', fn () => [
                'id' => $this->creadoPor?->id,
                'name' => $this->creadoPor?->name,
            ]),
            'anulado_por' => $this->whenLoaded('anuladoPor', fn () => [
                'id' => $this->anuladoPor?->id,
                'name' => $this->anuladoPor?->name,
            ]),
            'detalles' => ResumenDiarioDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
