<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use App\Services\ProductoService;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(private readonly ProductoService $productoService)
    {
    }

    public function index(Request $request)
    {
        $productos = Producto::query()
            ->with([
                'categoria',
                'marca',
                'laboratorio',
                'principioActivo',
                'principiosActivos',
                'accionTerapeutica',
                'afectacionIgv',
                'presentaciones.unidadMedida',
                'presentacionPrincipal.unidadMedida',
            ])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('categoria_id'), fn ($query) => $query->where('categoria_id', $request->integer('categoria_id')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($subquery) use ($buscar) {
                    $subquery->where('nombre', 'ilike', '%'.$buscar.'%')
                        ->orWhere('codigo_interno', 'ilike', '%'.$buscar.'%');
                });
            })
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 15));

        return ProductoResource::collection($productos);
    }

    public function store(StoreProductoRequest $request)
    {
        return (new ProductoResource($this->productoService->crear($request)))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $producto)
    {
        return new ProductoResource($this->loadProduct($this->findScoped($request, $producto)));
    }

    public function update(UpdateProductoRequest $request, int $producto)
    {
        $producto = $this->findScoped($request, $producto);

        return new ProductoResource($this->productoService->actualizar($request, $producto));
    }

    public function destroy(Request $request, int $producto)
    {
        $producto = $this->findScoped($request, $producto);

        $this->productoService->desactivar($producto);

        return response()->json(['message' => 'Producto desactivado correctamente.']);
    }

    protected function findScoped(Request $request, int $id): Producto
    {
        return Producto::query()
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }

    protected function loadProduct(Producto $producto): Producto
    {
        return $this->productoService->loadProduct($producto);
    }
}

