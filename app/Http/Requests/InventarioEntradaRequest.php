<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventarioEntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', Rule::exists('productos', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'producto_presentacion_id' => ['required', Rule::exists('producto_presentaciones', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'lote_id' => ['nullable', Rule::exists('lotes', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'cantidad_presentacion' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
            'referencia_tipo' => ['nullable', 'string', 'max:255'],
            'referencia_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function tenantId(): ?int
    {
        return $this->attributes->get('tenant')?->id;
    }

    protected function empresaId(): ?int
    {
        return $this->attributes->get('empresa')?->id;
    }

}
