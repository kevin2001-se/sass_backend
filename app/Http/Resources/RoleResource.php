<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'active' => (bool) $this->active,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id')->values(), []),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}