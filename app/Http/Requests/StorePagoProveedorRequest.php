<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cuenta_por_pagar_id' => ['required', 'integer'],
            'metodo_pago' => ['required', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha_pago' => ['required', 'date'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}