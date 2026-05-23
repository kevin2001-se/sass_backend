<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GuiaRemisionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'venta_id' => $this->venta_id,
            'comprobante_id' => $this->comprobante_id,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_completo' => $this->numero_completo ?: $this->numero_guia,
            'numero_guia' => $this->numero_guia ?: $this->numero_completo,
            'fecha_emision' => $this->fecha_emision?->toDateTimeString(),
            'fecha_traslado' => $this->fecha_traslado?->toDateString(),
            'tipo_referencia' => $this->tipo_referencia,
            'referencia_serie' => $this->referencia_serie,
            'referencia_numero' => $this->referencia_numero,
            'referencia' => $this->referencia_serie && $this->referencia_numero ? $this->referencia_serie.'-'.$this->referencia_numero : null,
            'venta' => $this->whenLoaded('venta', fn () => [
                'id' => $this->venta?->id,
                'numero' => $this->venta?->numero_comprobante,
                'tipo_comprobante' => $this->venta?->tipo_comprobante,
            ]),
            'comprobante' => $this->whenLoaded('comprobante', fn () => [
                'id' => $this->comprobante?->id,
                'tipo' => $this->comprobante?->tipo_comprobante,
                'numero' => $this->comprobante?->numero_comprobante,
                'estado_sunat' => $this->comprobante?->estado_sunat,
            ]),
            'motivo_traslado_codigo' => $this->motivo_traslado_codigo,
            'motivo_traslado_descripcion' => $this->motivo_traslado_descripcion,
            'motivo_traslado' => $this->whenLoaded('motivoTraslado', fn () => [
                'codigo' => $this->motivoTraslado?->codigo,
                'descripcion' => $this->motivoTraslado?->descripcion,
            ]),
            'modalidad_transporte' => $this->modalidad_transporte,
            'modalidad_transporte_detalle' => $this->whenLoaded('modalidadTransporte', fn () => [
                'codigo' => $this->modalidadTransporte?->codigo,
                'descripcion' => $this->modalidadTransporte?->descripcion,
            ]),
            'estado' => $this->estado,
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente?->id,
                'nombre' => $this->cliente?->razon_social ?: $this->cliente?->nombres,
                'numero_documento' => $this->cliente?->numero_documento,
            ]),
            'destinatario_tipo_documento' => $this->destinatario_tipo_documento,
            'destinatario_numero_documento' => $this->destinatario_numero_documento,
            'destinatario_nombre' => $this->destinatario_nombre,
            'punto_partida_ubigeo' => $this->punto_partida_ubigeo,
            'punto_partida_direccion' => $this->punto_partida_direccion,
            'punto_llegada_ubigeo' => $this->punto_llegada_ubigeo,
            'punto_llegada_direccion' => $this->punto_llegada_direccion,
            'conductor_tipo_documento' => $this->conductor_tipo_documento,
            'conductor_numero_documento' => $this->conductor_numero_documento,
            'conductor_nombre' => $this->conductor_nombre,
            'conductor_licencia' => $this->conductor_licencia,
            'vehiculo_placa' => $this->vehiculo_placa,
            'transportista_ruc' => $this->transportista_ruc ?: $this->transportista_numero_documento,
            'transportista_razon_social' => $this->transportista_razon_social,
            'peso_total' => (float) $this->peso_total,
            'unidad_peso' => $this->unidad_peso,
            'numero_bultos' => $this->numero_bultos,
            'observacion' => $this->observacion,
            'tiene_pdf_a4' => filled($this->pdf_a4_path),
            'tiene_ticket_80' => filled($this->ticket_80_path),
            'pdf_generado_at' => $this->pdf_generado_at?->toDateTimeString(),
            'ticket_generado_at' => $this->ticket_generado_at?->toDateTimeString(),
            'estado_sunat' => $this->estado_sunat ?: 'PENDIENTE',
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'hash' => $this->hash,
            'qr_text' => $this->qr_text,
            'intentos_envio' => (int) ($this->intentos_envio ?? 0),
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'updated_by_user' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ]),
            'detalles' => GuiaRemisionDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}