<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipo_comprobante' => ['nullable', 'string', 'max:30'],
            'serie' => ['nullable', 'string', 'max:10'],
            'numero' => ['nullable', 'string', 'max:20'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'estado_sunat' => ['nullable', 'string', 'max:20'],
            'tienda_id' => ['nullable', 'exists:tiendas,id'],
        ];
    }
}
