<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'in:RUC,DNI,CE,SIN_DOCUMENTO'],
            'numero_documento' => ['nullable', 'string', 'max:20', Rule::unique('proveedores', 'numero_documento')->where('empresa_id', $this->attributes->get('empresa')?->id)->ignore($this->route('proveedor'))],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
