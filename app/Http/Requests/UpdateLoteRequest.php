<?php

namespace App\Http\Requests;

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', Rule::exists('productos', 'id')->where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())],
            'codigo_lote' => ['required', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $loteId = (int) $this->route('lote');
            $producto = Producto::where('tenant_id', $this->tenantId())->where('empresa_id', $this->empresaId())->find($this->input('producto_id'));
            if (! $producto) return;
            if (! $producto->maneja_lote) $validator->errors()->add('producto_id', 'El producto no maneja lotes.');
            if ($producto->maneja_vencimiento && ! $this->filled('fecha_vencimiento')) $validator->errors()->add('fecha_vencimiento', 'La fecha de vencimiento es obligatoria para este producto.');

            $existe = Lote::where('empresa_id', $this->empresaId())->where('producto_id', $producto->id)->where('codigo_lote', $this->input('codigo_lote'))->where('id', '!=', $loteId)->exists();
            if ($existe) $validator->errors()->add('codigo_lote', 'El codigo de lote ya existe para este producto en la empresa.');
        });
    }

    protected function tenantId(): ?int { return $this->attributes->get('tenant')?->id; }
    protected function empresaId(): ?int { return $this->attributes->get('empresa')?->id; }
}
