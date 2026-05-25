<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'in:RUC,DNI,CE,SIN_DOCUMENTO'],
            'numero_documento' => ['required', 'string', 'max:20', Rule::unique('proveedores', 'numero_documento')->where('empresa_id', $this->attributes->get('empresa')?->id)],
            'razon_social' => ['required', 'string', 'min:2', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'digits:6'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipo = $this->input('tipo_documento');
            $numero = (string) $this->input('numero_documento');

            if ($tipo === 'RUC' && ! preg_match('/^\d{11}$/', $numero)) {
                $validator->errors()->add('numero_documento', 'El RUC debe tener 11 dígitos.');
            }

            if ($tipo === 'DNI' && ! preg_match('/^\d{8}$/', $numero)) {
                $validator->errors()->add('numero_documento', 'El DNI debe tener 8 dígitos.');
            }
        });
    }
}