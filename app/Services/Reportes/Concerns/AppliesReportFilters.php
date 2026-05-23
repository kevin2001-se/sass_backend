<?php

namespace App\Services\Reportes\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesReportFilters
{
    protected function rangoFechas(Builder $query, array $filtros, string $column): Builder
    {
        return $query
            ->when($filtros['fecha_inicio'] ?? null, fn ($q, $fecha) => $q->whereDate($column, '>=', $fecha))
            ->when($filtros['fecha_fin'] ?? null, fn ($q, $fecha) => $q->whereDate($column, '<=', $fecha));
    }

    protected function scopeBase(Builder $query, array $filtros, bool $tienda = true): Builder
    {
        $query->where('tenant_id', $filtros['tenant_id'])
            ->where('empresa_id', $filtros['empresa_id']);

        if ($tienda && ! empty($filtros['tienda_id'])) {
            $query->where('tienda_id', $filtros['tienda_id']);
        }

        return $query;
    }
}
