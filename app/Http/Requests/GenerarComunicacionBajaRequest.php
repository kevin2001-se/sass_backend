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
            'comprobantes.*' => ['integer', 'distinct', 'exists:comprobantes_electronicos,id'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
