<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parametros' => ['required', 'array', 'min:1'],
            'parametros.*.clave' => ['required', 'string', 'max:150', 'distinct'],
            'parametros.*.valor' => ['present'],
        ];
    }

    public function messages(): array
    {
        return [
            'parametros.required' => 'Debe enviar al menos un parametro.',
            'parametros.*.clave.required' => 'La clave del parametro es requerida.',
            'parametros.*.valor.present' => 'El valor del parametro debe estar presente.',
        ];
    }
}