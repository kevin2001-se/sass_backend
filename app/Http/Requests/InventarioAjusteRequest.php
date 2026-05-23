<?php

namespace App\Http\Requests;

class InventarioAjusteRequest extends InventarioEntradaRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tipo_ajuste' => ['required', 'in:POSITIVO,NEGATIVO'],
        ]);
    }
}
