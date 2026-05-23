<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSerieComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_comprobante' => ['required', 'in:NOTA_VENTA,BOLETA,FACTURA,NOTA_CREDITO,NOTA_DEBITO,GUIA_REMISION'],
            'serie' => ['required', 'string', 'max:10', Rule::unique('series_comprobantes', 'serie')
                ->where('empresa_id', $this->empresaId())
                ->where('tienda_id', $this->tiendaId())
                ->where('tipo_comprobante', $this->input('tipo_comprobante'))],
            'correlativo_actual' => ['sometimes', 'integer', 'min:0'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    protected function empresaId(): ?int
    {
        return $this->attributes->get('empresa')?->id;
    }

    protected function tiendaId(): ?int
    {
        return $this->attributes->get('tienda')?->id;
    }
}
