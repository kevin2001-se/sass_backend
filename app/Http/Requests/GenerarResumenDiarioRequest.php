<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarResumenDiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_resumen' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'incluir_boletas' => ['sometimes', 'boolean'],
            'incluir_notas_credito' => ['sometimes', 'boolean'],
            'incluir_notas_debito' => ['sometimes', 'boolean'],
        ];
    }
}
