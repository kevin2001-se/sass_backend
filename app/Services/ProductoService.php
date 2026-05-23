<?php

namespace App\Services;

use App\Models\AfectacionIgv;
use App\Models\PrincipioActivo;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoService
{
    public function __construct(private readonly ProductoConfiguracionService $configuracionService)
    {
    }

    public function crear(Request $request): Producto
    {
        return DB::transaction(function () use ($request) {
            $tenantId = $this->tenantId($request);
            $empresaId = $this->empresaId($request);
            $data = $this->productPayload($request);
            $data['tenant_id'] = $tenantId;
            $data['empresa_id'] = $empresaId;

            $configuracion = $this->configuracionService->obtenerOcrear($tenantId, $empresaId);
            if ($configuracion->autogenerar_codigo_interno || empty($data['codigo_interno'])) {
                $data['codigo_interno'] = $this->configuracionService->generarCodigoInterno($tenantId, $empresaId);
            }

            $this->normalizarProducto($data);
            $producto = Producto::create($data);
            $this->syncPrincipiosActivos($producto, $request->validated('principios_activos') ?? [], $tenantId, $empresaId);

            foreach ($request->validated('presentaciones') as $presentacion) {
                $producto->presentaciones()->create($this->presentationPayload($request, $presentacion));
            }

            return $this->loadProduct($producto);
        });
    }

    public function actualizar(Request $request, Producto $producto): Producto
    {
        return DB::transaction(function () use ($request, $producto) {
            $data = $this->productPayload($request);
            $this->normalizarProducto($data);
            $producto->update($data);
            $this->syncPrincipiosActivos($producto, $request->validated('principios_activos') ?? [], $this->tenantId($request), $this->empresaId($request));

            $presentacionIds = [];

            foreach ($request->validated('presentaciones') as $presentacion) {
                $presentacionData = $this->presentationPayload($request, $presentacion);

                if (! empty($presentacion['id'])) {
                    $producto->presentaciones()->where('id', $presentacion['id'])->update($presentacionData);
                    $presentacionIds[] = $presentacion['id'];
                    continue;
                }

                $nuevaPresentacion = $producto->presentaciones()->create($presentacionData);
                $presentacionIds[] = $nuevaPresentacion->id;
            }

            $producto->presentaciones()->whereNotIn('id', $presentacionIds)->update(['estado' => false, 'es_principal' => false]);

            return $this->loadProduct($producto->refresh());
        });
    }

    public function desactivar(Producto $producto): void
    {
        DB::transaction(function () use ($producto) {
            $producto->update(['estado' => false]);
            $producto->presentaciones()->update(['estado' => false]);
        });
    }

    protected function productPayload(Request $request): array
    {
        return collect($request->validated())
            ->except('presentaciones', 'principios_activos')
            ->toArray();
    }

    protected function normalizarProducto(array &$data): void
    {
        if (! empty($data['maneja_lote'])) {
            $data['maneja_vencimiento'] = true;
        }

        if (empty($data['afectacion_igv_id'])) {
            $data['afectacion_igv_id'] = AfectacionIgv::where('codigo', ! empty($data['afecto_igv']) ? '10' : '20')->value('id');
        }

        $afectacion = AfectacionIgv::find($data['afectacion_igv_id']);
        if ($afectacion) {
            $data['afecto_igv'] = (bool) $afectacion->aplica_igv;
        }

        if (! empty($data['principios_activos']) && empty($data['principio_activo_id'])) {
            $data['principio_activo_id'] = collect($data['principios_activos'])->first();
        }
    }

    protected function syncPrincipiosActivos(Producto $producto, array $principiosActivos, int $tenantId, int $empresaId): void
    {
        $ids = collect($principiosActivos)->filter()->unique()->values();

        if ($ids->isEmpty() && $producto->principio_activo_id) {
            $ids = collect([$producto->principio_activo_id]);
        }

        if ($ids->isNotEmpty()) {
            $validos = PrincipioActivo::where('tenant_id', $tenantId)
                ->where('empresa_id', $empresaId)
                ->whereIn('id', $ids)
                ->pluck('id');

            if ($validos->count() !== $ids->count()) {
                throw ValidationException::withMessages(['principios_activos' => ['Uno o mas principios activos no pertenecen a la empresa.']]);
            }

            $syncData = $validos->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId, 'empresa_id' => $empresaId]])->all();
            $producto->principiosActivos()->sync($syncData);
            return;
        }

        $producto->principiosActivos()->sync([]);
    }

    protected function presentationPayload(Request $request, array $presentacion): array
    {
        unset($presentacion['id']);

        $presentacion['tenant_id'] = $this->tenantId($request);
        $presentacion['empresa_id'] = $this->empresaId($request);

        if ($this->debeAutogenerarCodigoBarra($request) && empty($presentacion['codigo_barra'])) {
            $presentacion['codigo_barra'] = $this->configuracionService->generarCodigoBarra($this->tenantId($request), $this->empresaId($request));
        }

        return $presentacion;
    }

    protected function debeAutogenerarCodigoBarra(Request $request): bool
    {
        return $this->configuracionService->obtenerOcrear($this->tenantId($request), $this->empresaId($request))->autogenerar_codigo_barra;
    }

    public function loadProduct(Producto $producto): Producto
    {
        return $producto->load([
            'categoria',
            'marca',
            'laboratorio',
            'principioActivo',
            'principiosActivos',
            'accionTerapeutica',
            'afectacionIgv',
            'presentaciones.unidadMedida',
            'presentacionPrincipal.unidadMedida',
        ]);
    }

    protected function tenantId(Request $request): int
    {
        return $request->attributes->get('tenant')->id;
    }

    protected function empresaId(Request $request): int
    {
        return $request->attributes->get('empresa')->id;
    }
}
