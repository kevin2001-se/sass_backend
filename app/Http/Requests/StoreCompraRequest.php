<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;
        $empresaId = $this->attributes->get('empresa')?->id;

        return [
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'tipo_comprobante' => ['required', 'in:FACTURA,BOLETA,NOTA_VENTA,TICKET'],
            'serie' => ['required', 'string', 'max:20'],
            'numero' => ['required', 'string', 'max:30'],
            'tipo_compra' => ['required', 'in:CONTADO,CREDITO'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.producto_presentacion_id' => ['required', Rule::exists('producto_presentaciones', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.lote_id' => ['nullable', Rule::exists('lotes', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'detalles.*.lote.codigo_lote' => ['nullable', 'string', 'max:255'],
            'detalles.*.lote.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.cantidad_presentacion' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['sometimes', 'numeric', 'min:0'],
            'pagos' => ['sometimes', 'array'],
            'pagos.*.metodo_pago' => ['required_with:pagos', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
