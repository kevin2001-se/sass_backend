<?php

namespace App\Http\Resources;

use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\Venta;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteElectronicoResource extends JsonResource
{
    public function toArray($request): array
    {
        $venta = $this->relationLoaded('venta') ? $this->venta : null;
        $cliente = $venta && $venta->relationLoaded('cliente') ? $venta->cliente : null;
        $detalles = $venta && $venta->relationLoaded('detalles') ? $venta->detalles : collect();
        $notasCreditoCount = (int) ($this->notas_credito_count ?? 0);

        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'nota_electronica_id' => $this->nota_electronica_id,
            'comunicacion_baja_id' => $this->comunicacion_baja_id,
            'guia_remision_id' => $this->guia_remision_id,
            'documento_origen_tipo' => $this->documento_origen_tipo,
            'documento_origen_id' => $this->documento_origen_id,
            'tipo_comprobante' => $this->tipo_comprobante,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'numero_comprobante' => $this->numero_comprobante,
            'fecha_emision' => $this->fecha_emision?->toDateTimeString(),
            'moneda' => $this->moneda,
            'subtotal' => $venta ? (float) $venta->subtotal : 0,
            'total_igv' => $venta ? (float) $venta->total_igv : 0,
            'total_descuento' => $venta ? (float) $venta->total_descuento : 0,
            'total' => $venta ? (float) $venta->total : 0,
            'notas_credito_count' => $notasCreditoCount,
            'cliente' => $cliente ? [
                'id' => $cliente->id,
                'tipo_documento' => $cliente->tipo_documento,
                'numero_documento' => $cliente->numero_documento,
                'nombres' => $cliente->nombres,
                'razon_social' => $cliente->razon_social,
                'nombre' => $cliente->razon_social ?: $cliente->nombres,
            ] : null,
            'venta' => $venta ? [
                'id' => $venta->id,
                'numero_comprobante' => $venta->numero_comprobante,
                'subtotal' => (float) $venta->subtotal,
                'total_igv' => (float) $venta->total_igv,
                'total_descuento' => (float) $venta->total_descuento,
                'total' => (float) $venta->total,
                'notas_credito_count' => $notasCreditoCount,
                'detalles' => VentaDetalleResource::collection($detalles),
                'cliente' => $cliente ? [
                    'id' => $cliente->id,
                    'tipo_documento' => $cliente->tipo_documento,
                    'numero_documento' => $cliente->numero_documento,
                    'nombres' => $cliente->nombres,
                    'razon_social' => $cliente->razon_social,
                    'nombre' => $cliente->razon_social ?: $cliente->nombres,
                ] : null,
            ] : null,
            'estado_sunat' => $this->estado_sunat,
            'estado_baja' => $this->estado_baja ?: 'SIN_BAJA',
            'motivo_baja' => $this->motivo_baja,
            'mecanismo_baja' => $this->mecanismoBaja(),
            'fecha_solicitud_baja' => $this->fecha_solicitud_baja?->toDateTimeString(),
            'solicitado_baja_por' => $this->whenLoaded('solicitadoBajaPor', fn () => $this->solicitadoBajaPor ? [
                'id' => $this->solicitadoBajaPor->id,
                'name' => $this->solicitadoBajaPor->name,
            ] : null),
            'baja_historial' => $this->whenLoaded('bajaHistorial', fn () => ComprobanteBajaHistorialResource::collection($this->bajaHistorial)),
            'codigo_respuesta' => $this->codigo_respuesta,
            'mensaje_respuesta' => $this->mensaje_respuesta,
            'hash' => $this->hash,
            'qr_text' => $this->qr_text,
            'tiene_xml' => filled($this->xml_path),
            'tiene_cdr' => filled($this->cdr_path),
            'tiene_pdf_a4' => filled($this->pdf_a4_path),
            'tiene_ticket_80' => filled($this->ticket_80_path),
            'tiene_ticket_58' => filled($this->ticket_58_path),
            'intentos_envio' => $this->intentos_envio,
            'enviado_at' => $this->enviado_at?->toDateTimeString(),
            'aceptado_at' => $this->aceptado_at?->toDateTimeString(),
            'rechazado_at' => $this->rechazado_at?->toDateTimeString(),
            'dado_baja_at' => $this->dado_baja_at?->toDateTimeString(),
            'pdf_generado_at' => $this->pdf_generado_at?->toDateTimeString(),
            'ticket_generado_at' => $this->ticket_generado_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
    protected function mecanismoBaja(): ?string
    {
        return match ($this->tipo_comprobante) {
            Venta::BOLETA => 'RESUMEN_DIARIO',
            Venta::FACTURA => 'COMUNICACION_BAJA',
            'NOTA_CREDITO' => $this->notaCreditoOrigenBoleta() ? 'RESUMEN_DIARIO' : 'COMUNICACION_BAJA',
            'NOTA_DEBITO' => $this->notaDebitoOrigenBoleta() ? 'RESUMEN_DIARIO' : 'COMUNICACION_BAJA',
            default => null,
        };
    }

    protected function notaCreditoOrigenBoleta(): bool
    {
        $nota = NotaCredito::with('comprobante')
            ->where(function ($query) {
                $query->where('id', $this->documento_origen_id)
                    ->orWhere('id', $this->nota_electronica_id)
                    ->orWhere('numero_completo', $this->numero_comprobante);
            })
            ->first();

        return $nota?->comprobante?->tipo_comprobante === Venta::BOLETA;
    }

    protected function notaDebitoOrigenBoleta(): bool
    {
        $nota = NotaDebito::with('comprobante')
            ->where(function ($query) {
                $query->where('id', $this->documento_origen_id)
                    ->orWhere('id', $this->nota_electronica_id)
                    ->orWhere('numero_completo', $this->numero_comprobante);
            })
            ->first();

        return $nota?->comprobante?->tipo_comprobante === Venta::BOLETA;
    }
}
