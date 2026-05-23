<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotaCreditoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'comprobante_referencia_id' => ['required', 'integer'],
            'motivo_codigo' => ['required', 'in:01,02,03,04,05,06,07,08,09,10'],
            'motivo_descripcion' => ['required', 'string', 'max:255'],
            'afecta_stock' => ['sometimes', 'boolean'],
            'afecta_caja' => ['sometimes', 'boolean'],
            'detalles' => ['sometimes', 'array'],
            'detalles.*.venta_detalle_id' => ['required_with:detalles', 'integer'],
            'detalles.*.cantidad_presentacion' => ['required_with:detalles', 'numeric', 'min:0.01'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
