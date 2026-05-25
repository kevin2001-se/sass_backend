<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $detalles = collect($this->input('detalles', []))->map(function ($detalle) {
            if (isset($detalle['costo_unitario']) && ! isset($detalle['precio_unitario'])) {
                $detalle['precio_unitario'] = $detalle['costo_unitario'];
            }
            if (! empty($detalle['codigo_lote'])) {
                $detalle['lote'] = [
                    'codigo_lote' => $detalle['codigo_lote'],
                    'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                ];
            }
            return $detalle;
        })->all();

        $this->merge([
            'tipo_comprobante' => $this->input('tipo_comprobante', $this->input('tipo_documento')),
            'numero' => $this->input('numero', $this->input('numero_documento', $this->input('correlativo'))),
            'tipo_compra' => $this->input('tipo_compra', $this->input('tipo_pago')),
            'detalles' => $detalles,
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;
        $empresaId = $this->attributes->get('empresa')?->id;

        return [
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'tipo_comprobante' => ['required', 'in:FACTURA,BOLETA,NOTA_COMPRA,GUIA_PROVEEDOR'],
            'serie' => ['required', 'string', 'max:20'],
            'numero' => ['required', 'string', 'max:30'],
            'tipo_compra' => ['required', 'in:CONTADO,CREDITO'],
            'moneda' => ['sometimes', 'in:PEN,USD'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.producto_presentacion_id' => ['required', Rule::exists('producto_presentaciones', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.lote_id' => ['nullable', Rule::exists('lotes', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.codigo_lote' => ['nullable', 'string', 'max:255'],
            'detalles.*.lote.codigo_lote' => ['nullable', 'string', 'max:255'],
            'detalles.*.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.lote.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.cantidad_presentacion' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['sometimes', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}