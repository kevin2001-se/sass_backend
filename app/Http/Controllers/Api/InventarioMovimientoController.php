<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioAjusteRequest;
use App\Http\Requests\InventarioEntradaRequest;
use App\Http\Requests\InventarioSalidaRequest;
use App\Http\Resources\InventarioMovimientoResource;
use App\Models\InventarioMovimiento;
use App\Services\InventarioService;
use Illuminate\Http\Request;

class InventarioMovimientoController extends Controller
{
    public function __construct(private readonly InventarioService $inventarioService)
    {
    }

    public function index(Request $request)
    {
        $movimientos = InventarioMovimiento::with(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'presentacion.unidadMedida', 'lote', 'user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('producto_id'), fn ($query) => $query->where('producto_id', $request->integer('producto_id')))
            ->when($request->filled('lote_id'), fn ($query) => $query->where('lote_id', $request->integer('lote_id')))
            ->when($request->filled('tipo_movimiento'), fn ($query) => $query->where('tipo_movimiento', $request->input('tipo_movimiento')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return InventarioMovimientoResource::collection($movimientos);
    }

    public function entrada(InventarioEntradaRequest $request)
    {
        $movimiento = $this->inventarioService->aumentarStock($this->inventoryPayload($request));

        return (new InventarioMovimientoResource($this->loadMovimiento($movimiento)))->response()->setStatusCode(201);
    }

    public function salida(InventarioSalidaRequest $request)
    {
        $movimiento = $this->inventarioService->disminuirStock($this->inventoryPayload($request));

        return (new InventarioMovimientoResource($this->loadMovimiento($movimiento)))->response()->setStatusCode(201);
    }

    public function ajuste(InventarioAjusteRequest $request)
    {
        $movimiento = $this->inventarioService->ajustarStock($this->inventoryPayload($request));

        return (new InventarioMovimientoResource($this->loadMovimiento($movimiento)))->response()->setStatusCode(201);
    }

    public function kardex(Request $request, int $productoId)
    {
        $movimientos = $this->inventarioService->obtenerKardexProducto(
            $productoId,
            $request->attributes->get('tienda')->id,
            $request->attributes->get('empresa')->id,
            $request->attributes->get('tenant')->id,
            $request->integer('per_page', 15)
        );

        return InventarioMovimientoResource::collection($movimientos);
    }

    protected function inventoryPayload(Request $request): array
    {
        return array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]);
    }

    protected function loadMovimiento(InventarioMovimiento $movimiento): InventarioMovimiento
    {
        return $movimiento->load(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'presentacion.unidadMedida', 'lote', 'user']);
    }
}
