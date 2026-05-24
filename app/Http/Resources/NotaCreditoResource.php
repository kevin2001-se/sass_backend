<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotaCreditoResource extends JsonResource
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
            'numero_completo' => $this->numero_completo,
            'motivo_codigo' => $this->motivo_codigo,
            'motivo_descripcion' => $this->motivo_descripcion,
            'motivo' => new MotivoNotaCreditoResource($this->whenLoaded('motivo')),
            'tipo_nota' => $this->tipo_nota,
            'afecta_stock' => $this->afecta_stock,
            'afecta_caja' => $this->afecta_caja,
            'stock_aplicado' => $this->stock_aplicado,
            'caja_aplicada' => $this->caja_aplicada,
            'stock_aplicado_at' => $this->stock_aplicado_at?->toDateTimeString(),
            'caja_aplicada_at' => $this->caja_aplicada_at?->toDateTimeString(),
            'caja_movimiento_id' => $this->caja_movimiento_id,
            'mensaje_efectos' => $this->mensajeEfectos(),
            'subtotal' => $this->subtotal,
            'total_descuento' => $this->total_descuento,
            'total_igv' => $this->total_igv,
            'total' => $this->total,
            'observacion' => $this->observacion,
            'motivo_anulacion' => $this->motivo_anulacion,
            'estado_sunat' => $this->estado_sunat,
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'hash' => $this->hash,
            'qr_text' => $this->qr_text,
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'tiene_pdf_a4' => filled($this->pdf_a4_path),
            'tiene_ticket_80' => filled($this->ticket_80_path),
            'intentos_envio' => $this->intentos_envio,
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'pdf_generado_at' => $this->pdf_generado_at?->toDateTimeString(),
            'ticket_generado_at' => $this->ticket_generado_at?->toDateTimeString(),
            'estado' => $this->estado,
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'venta' => new VentaResource($this->whenLoaded('venta')),
            'comprobante' => new ComprobanteElectronicoResource($this->whenLoaded('comprobante')),
            'cliente' => $this->whenLoaded('venta', fn () => $this->venta?->relationLoaded('cliente') ? [
                'id' => $this->venta->cliente?->id,
                'tipo_documento' => $this->venta->cliente?->tipo_documento,
                'numero_documento' => $this->venta->cliente?->numero_documento,
                'nombre' => $this->venta->cliente?->razon_social ?: $this->venta->cliente?->nombres,
            ] : null),
            'detalles' => NotaCreditoDetalleResource::collection($this->whenLoaded('detalles')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'anulado_by' => $this->anulado_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function mensajeEfectos(): ?string
    {
        if ($this->estado === 'ANULADA') {
            return null;
        }

        if ($this->afecta_stock && ! $this->stock_aplicado) {
            return 'Stock pendiente de aplicar.';
        }

        if ($this->afecta_caja && ! $this->caja_aplicada) {
            return 'Caja pendiente de aplicar.';
        }

        return null;
    }
}
