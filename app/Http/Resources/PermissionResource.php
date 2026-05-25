<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'active' => (bool) $this->active,
            'modulo' => explode('.', $this->name)[0] ?? 'general',
        ];
    }
}