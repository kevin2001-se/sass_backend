<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'empresa_id' => $this->empresa_id,
            'categoria_id' => $this->categoria_id,
            'marca_id' => $this->marca_id,
            'laboratorio_id' => $this->laboratorio_id,
            'principio_activo_id' => $this->principio_activo_id,
            'principios_activos_ids' => $this->whenLoaded('principiosActivos', fn () => $this->principiosActivos->pluck('id')->values()),
            'accion_terapeutica_id' => $this->accion_terapeutica_id,
            'afectacion_igv_id' => $this->afectacion_igv_id,
            'codigo_interno' => $this->codigo_interno,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'concentracion' => $this->concentracion,
            'requiere_receta' => $this->requiere_receta,
            'maneja_lote' => $this->maneja_lote,
            'maneja_vencimiento' => $this->maneja_vencimiento,
            'afecto_igv' => $this->afecto_igv,
            'estado' => $this->estado,
            'categoria' => new CatalogoResource($this->whenLoaded('categoria')),
            'marca' => new CatalogoResource($this->whenLoaded('marca')),
            'laboratorio' => new CatalogoResource($this->whenLoaded('laboratorio')),
            'principio_activo' => new CatalogoResource($this->whenLoaded('principioActivo')),
            'principios_activos' => CatalogoResource::collection($this->whenLoaded('principiosActivos')),
            'accion_terapeutica' => new CatalogoResource($this->whenLoaded('accionTerapeutica')),
            'afectacion_igv' => new AfectacionIgvResource($this->whenLoaded('afectacionIgv')),
            'presentaciones' => ProductoPresentacionResource::collection($this->whenLoaded('presentaciones')),
            'presentacion_principal' => new ProductoPresentacionResource($this->whenLoaded('presentacionPrincipal')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
