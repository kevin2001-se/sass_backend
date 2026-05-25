<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpresaConfiguracionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ruc' => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['required', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'digits:6'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}