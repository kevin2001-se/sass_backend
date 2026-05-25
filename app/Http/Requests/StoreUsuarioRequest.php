<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->attributes->get('empresa')?->id;
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'estado' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('empresa_id', $empresaId)],
            'tiendas' => ['required', 'array', 'min:1'],
            'tiendas.*' => ['integer', Rule::exists('tiendas', 'id')->where('empresa_id', $empresaId)],
            'tienda_activa_id' => ['nullable', 'integer', Rule::exists('tiendas', 'id')->where('empresa_id', $empresaId)],
        ];
    }
}