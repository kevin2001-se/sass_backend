<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'tienda_id' => $this->tienda_id,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => new ProveedorResource($this->whenLoaded('proveedor')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tienda' => new TiendaResource($this->whenLoaded('tienda')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'anulado_by' => $this->anulado_by,
            'tipo_documento' => $this->tipo_comprobante,
            'tipo_comprobante' => $this->tipo_comprobante,
            'serie' => $this->serie,
            'correlativo' => $this->numero,
            'numero' => $this->numero,
            'numero_documento' => trim(($this->serie ? $this->serie.'-' : '').$this->numero),
            'tipo_pago' => $this->tipo_compra,
            'tipo_compra' => $this->tipo_compra,
            'moneda' => $this->moneda ?? 'PEN',
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'subtotal' => $this->subtotal,
            'total_igv' => $this->total_igv,
            'total_descuento' => $this->total_descuento,
            'total' => $this->total,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'motivo_anulacion' => $this->motivo_anulacion,
            'anulado_at' => $this->anulado_at?->toDateTimeString(),
            'detalles' => CompraDetalleResource::collection($this->whenLoaded('detalles')),
            'movimientos_inventario' => InventarioMovimientoResource::collection($this->whenLoaded('movimientosInventario')),
            'tiene_pdf' => filled($this->pdf_path),
            'pdf_generado_at' => $this->pdf_generado_at?->toDateTimeString(),
            'pagos' => CompraPagoResource::collection($this->whenLoaded('pagos')),
            'cuenta_por_pagar' => new CuentaPorPagarResource($this->whenLoaded('cuentaPorPagar')),
        ];
    }
}
