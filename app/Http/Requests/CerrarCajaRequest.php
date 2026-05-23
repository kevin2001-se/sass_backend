<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_cierre_real' => ['required', 'numeric', 'min:0'],
            'observacion_cierre' => ['nullable', 'string'],
        ];
    }
}
