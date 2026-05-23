<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductoPresentacionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'producto_id' => $this->producto_id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'unidad_medida' => new CatalogoResource($this->whenLoaded('unidadMedida')),
            'nombre' => $this->nombre,
            'codigo_barra' => $this->codigo_barra,
            'factor_conversion' => $this->factor_conversion,
            'precio_compra' => $this->precio_compra,
            'precio_venta' => $this->precio_venta,
            'es_principal' => $this->es_principal,
            'estado' => $this->estado,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
