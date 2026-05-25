<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTiendaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('tiendas', 'codigo')->where('empresa_id', $this->attributes->get('empresa')?->id)],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'digits:6'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}