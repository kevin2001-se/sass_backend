<?php

namespace App\Http\Requests;

use App\Models\ProductoConfiguracion;
use App\Models\ProductoPresentacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $empresaId = $this->empresaId();

        return [
            'categoria_id' => ['required', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'marca_id' => ['nullable', Rule::exists('marcas', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'laboratorio_id' => ['nullable', Rule::exists('laboratorios', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'principio_activo_id' => ['nullable', Rule::exists('principios_activos', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'principios_activos' => ['nullable', 'array'],
            'principios_activos.*' => ['integer', 'distinct', Rule::exists('principios_activos', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'accion_terapeutica_id' => ['nullable', Rule::exists('acciones_terapeuticas', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'afectacion_igv_id' => ['required', Rule::exists('afectaciones_igv', 'id')->where('estado', true)],
            'codigo_interno' => ['nullable', 'string', 'max:255', Rule::unique('productos', 'codigo_interno')->where('empresa_id', $empresaId)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'concentracion' => ['nullable', 'string', 'max:255'],
            'requiere_receta' => ['sometimes', 'boolean'],
            'maneja_lote' => ['sometimes', 'boolean'],
            'maneja_vencimiento' => ['sometimes', 'boolean'],
            'afecto_igv' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
            'presentaciones' => ['required', 'array', 'min:1'],
            'presentaciones.*.unidad_medida_id' => ['required', Rule::exists('unidades_medida', 'id')->where('tenant_id', $tenantId)->where('empresa_id', $empresaId)],
            'presentaciones.*.nombre' => ['required', 'string', 'max:255'],
            'presentaciones.*.codigo_barra' => ['nullable', 'string', 'max:255', 'distinct'],
            'presentaciones.*.factor_conversion' => ['required', 'numeric', 'gt:0'],
            'presentaciones.*.precio_compra' => ['nullable', 'numeric', 'gte:0'],
            'presentaciones.*.precio_venta' => ['required', 'numeric', 'gte:0'],
            'presentaciones.*.es_principal' => ['required', 'boolean'],
            'presentaciones.*.estado' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateBusinessRules($validator));
    }

    protected function validateBusinessRules(Validator $validator): void
    {
        if ($this->boolean('maneja_lote') && $this->has('maneja_vencimiento') && ! $this->boolean('maneja_vencimiento')) {
            $validator->errors()->add('maneja_vencimiento', 'En farmacia, si el producto maneja lote tambien debe manejar vencimiento.');
        }

        $config = ProductoConfiguracion::where('empresa_id', $this->empresaId())->where('estado', true)->first();
        if (! ($config?->autogenerar_codigo_interno) && ! $this->filled('codigo_interno')) {
            $validator->errors()->add('codigo_interno', 'El codigo interno es obligatorio cuando no se autogenera.');
        }

        $principios = collect($this->input('principios_activos', []))->filter();
        if ($principios->duplicates()->isNotEmpty()) {
            $validator->errors()->add('principios_activos', 'No debe repetir principios activos.');
        }

        $presentaciones = collect($this->input('presentaciones', []));
        if ($presentaciones->where('es_principal', true)->count() !== 1) {
            $validator->errors()->add('presentaciones', 'Debe existir exactamente una presentacion principal.');
        }

        $codigos = $presentaciones->pluck('codigo_barra')->filter()->values();
        if ($codigos->isNotEmpty() && ProductoPresentacion::where('empresa_id', $this->empresaId())->whereIn('codigo_barra', $codigos)->exists()) {
            $validator->errors()->add('presentaciones.*.codigo_barra', 'El codigo de barra de la presentacion debe ser unico por empresa.');
        }
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
