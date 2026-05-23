<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarComunicacionBajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_baja' => ['required', 'date'],
            'comprobantes' => ['required', 'array', 'min:1'],
            'comprobantes.*.comprobante_electronico_id' => ['required', 'integer', 'exists:comprobantes_electronicos,id', 'distinct'],
            'comprobantes.*.motivo_baja' => ['required', 'string', 'max:255'],
        ];
    }
}
