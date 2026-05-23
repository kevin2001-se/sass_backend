<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Services\InventarioService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private readonly InventarioService $inventarioService)
    {
    }

    public function index(Request $request)
    {
        $stocks = Stock::with(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'lote'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('producto_id'), fn ($query) => $query->where('producto_id', $request->integer('producto_id')))
            ->orderBy('producto_id')
            ->paginate($request->integer('per_page', 15));

        return StockResource::collection($stocks);
    }

    public function producto(Request $request, int $productoId)
    {
        $stocks = $this->inventarioService->obtenerStockProducto(
            $productoId,
            $request->attributes->get('tienda')->id,
            $request->attributes->get('empresa')->id,
            $request->attributes->get('tenant')->id
        );

        return StockResource::collection($stocks);
    }

    public function alertas(Request $request)
    {
        $stocks = Stock::with(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'lote'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->where('estado', true)
            ->whereNotNull('cantidad_minima')
            ->whereColumn('cantidad_actual', '<=', 'cantidad_minima')
            ->orderBy('cantidad_actual')
            ->paginate($request->integer('per_page', 15));

        return StockResource::collection($stocks);
    }
}
