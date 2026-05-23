<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

class PosClienteService
{
    public function buscar(array $context, ?string $query = null, int $limit = 20): Collection
    {
        $query = trim((string) $query);

        return Cliente::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('empresa_id', $context['empresa_id'])
            ->where('estado', true)
            ->when($query !== '', function ($builder) use ($query) {
                $like = '%'.mb_strtolower($query).'%';

                $builder->where(function ($subquery) use ($like) {
                    $subquery->whereRaw("LOWER(COALESCE(numero_documento, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(nombres, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(razon_social, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(telefono, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE ?", [$like]);
                });
            })
            ->orderByRaw("CASE WHEN tipo_documento = 'RUC' THEN razon_social ELSE nombres END ASC")
            ->limit($limit)
            ->get();
    }
}