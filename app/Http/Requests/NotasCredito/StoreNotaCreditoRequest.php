<?php

namespace App\Http\Requests\NotasCredito;

use App\Models\NotaCredito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotaCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comprobante_id' => ['required', 'integer', 'exists:comprobantes_electronicos,id'],
            'motivo_codigo' => ['required', 'string', 'size:2', 'exists:motivos_nota_credito,codigo'],
            'motivo_descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_nota' => ['required', Rule::in([NotaCredito::TOTAL, NotaCredito::PARCIAL])],
            'afecta_stock' => ['sometimes', 'boolean'],
            'afecta_caja' => ['sometimes', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'metodo_pago_devolucion' => ['nullable', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'observacion_caja' => ['nullable', 'string', 'max:500'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.venta_detalle_id' => ['required_with:detalles', 'integer', 'exists:venta_detalles,id'],
            'detalles.*.cantidad' => ['required_with:detalles', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('motivo_codigo') === '08' && $this->input('tipo_nota') !== NotaCredito::PARCIAL) {
                $validator->errors()->add('tipo_nota', 'El motivo devolucion parcial requiere tipo_nota PARCIAL.');
            }

            if ($this->input('tipo_nota') === NotaCredito::PARCIAL && count($this->input('detalles', [])) < 1) {
                $validator->errors()->add('detalles', 'El campo detalles debe tener al menos 1 item para una nota parcial.');
            }
        });
    }
}

