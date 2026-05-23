<?php

namespace App\Services;

use App\Models\ProductoConfiguracion;
use Illuminate\Support\Facades\DB;

class ProductoConfiguracionService
{
    public function obtenerOcrear(int $tenantId, int $empresaId): ProductoConfiguracion
    {
        return ProductoConfiguracion::firstOrCreate([
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
            'estado' => true,
        ], [
            'autogenerar_codigo_interno' => true,
            'prefijo_codigo_interno' => 'PROD',
            'ultimo_correlativo_codigo_interno' => 0,
            'autogenerar_codigo_barra' => false,
            'prefijo_codigo_barra' => null,
            'ultimo_correlativo_codigo_barra' => 0,
        ]);
    }

    public function actualizar(int $tenantId, int $empresaId, array $data): ProductoConfiguracion
    {
        return DB::transaction(function () use ($tenantId, $empresaId, $data) {
            $configuracion = $this->obtenerOcrear($tenantId, $empresaId);

            $configuracion->update([
                'autogenerar_codigo_interno' => (bool) ($data['autogenerar_codigo_interno'] ?? $configuracion->autogenerar_codigo_interno),
                'prefijo_codigo_interno' => $data['prefijo_codigo_interno'] ?? $configuracion->prefijo_codigo_interno,
                'autogenerar_codigo_barra' => (bool) ($data['autogenerar_codigo_barra'] ?? $configuracion->autogenerar_codigo_barra),
                'prefijo_codigo_barra' => $data['prefijo_codigo_barra'] ?? null,
            ]);

            return $configuracion->refresh();
        });
    }

    public function generarCodigoInterno(int $tenantId, int $empresaId): string
    {
        $configuracion = $this->obtenerConfiguracionBloqueada($tenantId, $empresaId);
        $configuracion->increment('ultimo_correlativo_codigo_interno');
        $configuracion->refresh();

        $prefijo = $configuracion->prefijo_codigo_interno ?: 'PROD';

        return $prefijo.str_pad((string) $configuracion->ultimo_correlativo_codigo_interno, 6, '0', STR_PAD_LEFT);
    }

    public function generarCodigoBarra(int $tenantId, int $empresaId): string
    {
        $configuracion = $this->obtenerConfiguracionBloqueada($tenantId, $empresaId);
        $configuracion->increment('ultimo_correlativo_codigo_barra');
        $configuracion->refresh();

        $prefijo = $configuracion->prefijo_codigo_barra ?: '';

        return $prefijo.str_pad((string) $configuracion->ultimo_correlativo_codigo_barra, 8, '0', STR_PAD_LEFT);
    }

    private function obtenerConfiguracionBloqueada(int $tenantId, int $empresaId): ProductoConfiguracion
    {
        $this->obtenerOcrear($tenantId, $empresaId);

        return ProductoConfiguracion::query()
            ->where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
