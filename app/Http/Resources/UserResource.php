<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'estado' => (bool) ($this->estado ?? true),
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ] : null,
            'roles' => $this->role ? [[
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]] : [],
            'permisos' => $this->whenLoaded('role', fn () => $this->role?->permissions->pluck('name')->values() ?? []),
            'tenant' => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
            ],
            'empresa' => [
                'id' => $this->empresa?->id,
                'nombre' => $this->empresa?->nombre,
            ],
            'tienda_activa' => $this->tiendaActiva ? [
                'id' => $this->tiendaActiva->id,
                'nombre' => $this->tiendaActiva->nombre,
                'direccion' => $this->tiendaActiva->direccion,
            ] : null,
            'tiendas' => TiendaResource::collection($this->whenLoaded('tiendas')),
            'tiendas_disponibles' => TiendaResource::collection($this->whenLoaded('tiendasActivas')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
