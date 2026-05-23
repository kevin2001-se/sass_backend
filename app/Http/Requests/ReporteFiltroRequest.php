<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;
        $empresaId = $this->attributes->get('empresa')?->id;

        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tienda_id' => ['nullable', Rule::exists('tiendas', 'id')->where('empresa_id', $empresaId)],
            'usuario_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'proveedor_id' => ['nullable', Rule::exists('proveedores', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'producto_id' => ['nullable', Rule::exists('productos', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'estado' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filtros(): array
    {
        $tiendaId = (int) $this->input('tienda_id', $this->attributes->get('tienda')->id);
        $tiendasPermitidas = $this->user()->tiendasActivas()->pluck('tiendas.id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($tiendaId, $tiendasPermitidas, true)) {
            abort(403, 'No tiene acceso a la tienda solicitada.');
        }

        return array_merge($this->validated(), [
            'tenant_id' => $this->attributes->get('tenant')->id,
            'empresa_id' => $this->attributes->get('empresa')->id,
            'tienda_id' => $tiendaId,
        ]);
    }
}
