<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'tipo_comprobante' => ['required', 'in:NOTA_VENTA,BOLETA,FACTURA'],
            'tipo_venta' => ['required', 'in:CONTADO,CREDITO'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'detalles.*.producto_presentacion_id' => ['required', Rule::exists('producto_presentaciones', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'detalles.*.lote_id' => ['nullable', Rule::exists('lotes', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'detalles.*.cantidad_presentacion' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.descuento' => ['sometimes', 'numeric', 'min:0'],
            'pagos' => ['sometimes', 'array'],
            'pagos.*.metodo_pago' => ['required_with:pagos', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    protected function tenantId(): ?int
    {
        return $this->attributes->get('tenant')?->id;
    }

    protected function empresaId(): ?int
    {
        return $this->attributes->get('empresa')?->id;
    }

}
