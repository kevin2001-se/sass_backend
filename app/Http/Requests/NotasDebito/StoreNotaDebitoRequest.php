<?php

namespace App\Http\Requests\NotasDebito;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotaDebitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comprobante_id' => ['required', 'integer', 'exists:comprobantes_electronicos,id'],
            'motivo_codigo' => ['required', 'string', 'size:2', 'exists:motivos_nota_debito,codigo'],
            'motivo_descripcion' => ['nullable', 'string', 'max:255'],
            'afecta_caja' => ['sometimes', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'metodo_pago_cobro' => ['nullable', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'observacion_caja' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
        ];
    }
}