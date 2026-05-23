<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoElectronicoResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource instanceof \App\Models\ComprobanteElectronico ? null : $this->resource;
        $comprobante = $data['comprobante'] ?? $this->resource;

        return [
            'id' => $comprobante->id,
            'tipo_comprobante' => $comprobante->tipo_comprobante,
            'serie' => $comprobante->serie,
            'correlativo' => $comprobante->correlativo,
            'numero_comprobante' => $comprobante->numero_comprobante,
            'fecha_emision' => $comprobante->fecha_emision?->toDateString(),
            'cliente' => $data['cliente']['nombre'] ?? $this->clienteResumen($comprobante),
            'total' => (float) ($data['totales']['total'] ?? $this->totalResumen($comprobante)),
            'estado_sunat' => $comprobante->estado_sunat,
            'codigo_respuesta' => $comprobante->codigo_respuesta,
            'mensaje_respuesta' => $comprobante->mensaje_respuesta,
            'hash' => $comprobante->hash,
            'qr_text' => $comprobante->qr_text,
            'tiene_xml' => filled($comprobante->xml_path),
            'tiene_cdr' => filled($comprobante->cdr_path),
            'tiene_pdf_a4' => filled($comprobante->pdf_a4_path),
            'tiene_ticket_80' => filled($comprobante->ticket_80_path),
            'tiene_ticket_58' => filled($comprobante->ticket_58_path),
            'detalles' => DocumentoElectronicoDetalleResource::collection($data['detalles'] ?? []),
        ];
    }

    protected function clienteResumen($comprobante): ?string
    {
        return $comprobante->venta?->cliente?->razon_social
            ?: $comprobante->venta?->cliente?->nombres
            ?: $comprobante->notaElectronica?->venta?->cliente?->razon_social
            ?: $comprobante->notaElectronica?->venta?->cliente?->nombres
            ?: $comprobante->guiaRemision?->cliente?->razon_social
            ?: $comprobante->guiaRemision?->cliente?->nombres
            ?: $comprobante->guiaRemision?->proveedor?->razon_social;
    }

    protected function totalResumen($comprobante): float
    {
        return (float) ($comprobante->venta?->total
            ?? $comprobante->notaElectronica?->total
            ?? 0);
    }
}
