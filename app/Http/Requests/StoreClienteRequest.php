<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'in:DNI,RUC,CE,SIN_DOCUMENTO'],
            'numero_documento' => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'numero_documento')->where('empresa_id', $this->empresaId())],
            'nombres' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    protected function empresaId(): ?int
    {
        return $this->attributes->get('empresa')?->id;
    }
}
