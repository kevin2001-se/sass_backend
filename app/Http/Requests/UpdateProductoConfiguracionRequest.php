<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'autogenerar_codigo_interno' => ['sometimes', 'boolean'],
            'prefijo_codigo_interno' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'autogenerar_codigo_barra' => ['sometimes', 'boolean'],
            'prefijo_codigo_barra' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'prefijo_codigo_interno.regex' => 'El prefijo interno solo puede contener letras, numeros, guiones y guiones bajos.',
            'prefijo_codigo_barra.regex' => 'El prefijo de barras solo puede contener letras, numeros, guiones y guiones bajos.',
        ];
    }
}
