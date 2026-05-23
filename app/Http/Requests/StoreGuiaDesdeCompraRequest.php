<?php

namespace App\Http\Requests;

class StoreGuiaDesdeCompraRequest extends StoreGuiaRemisionRequest
{
    public function rules(): array
    {
        return $this->baseRules();
    }
}
