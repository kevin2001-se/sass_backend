<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarIngresoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
