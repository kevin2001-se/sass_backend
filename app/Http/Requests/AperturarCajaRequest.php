<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AperturarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_apertura' => ['required', 'numeric', 'min:0'],
            'observacion_apertura' => ['nullable', 'string'],
        ];
    }
}
