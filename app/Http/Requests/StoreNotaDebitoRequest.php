<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotaDebitoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'comprobante_referencia_id' => ['required', 'integer'],
            'motivo_codigo' => ['required', 'in:01,02,03'],
            'motivo_descripcion' => ['required', 'string', 'max:255'],
            'afecta_caja' => ['sometimes', 'boolean'],
            'metodo_pago' => ['sometimes', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad_presentacion' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.afecto_igv' => ['sometimes', 'boolean'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
