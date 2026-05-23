<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularCompraRequest;
use App\Http\Requests\StoreCompraRequest;
use App\Http\Resources\CompraResource;
use App\Models\Compra;
use App\Services\CompraService;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compraService) {}

    public function index(Request $request)
    {
        $compras = Compra::with(['proveedor', 'user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->orderByDesc('fecha_emision')->orderByDesc('id')->paginate($request->integer('per_page', 15));
        return CompraResource::collection($compras);
    }

    public function store(StoreCompraRequest $request)
    {
        $compra = $this->compraService->registrarCompra($this->payload($request));
        return (new CompraResource($compra))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $compra)
    {
        return new CompraResource($this->findScoped($request, $compra)->load(['proveedor', 'user', 'detalles.producto', 'detalles.presentacion.unidadMedida', 'detalles.lote', 'pagos', 'cuentaPorPagar.pagos']));
    }

    public function anular(AnularCompraRequest $request, int $compra)
    {
        $compra = $this->compraService->anularCompra($compra, $request->validated('motivo'), $this->scope($request));
        return new CompraResource($compra);
    }

    protected function payload(Request $request): array { return array_merge($request->validated(), $this->scope($request)); }
    protected function scope(Request $request): array { return ['tenant_id' => $request->attributes->get('tenant')->id, 'empresa_id' => $request->attributes->get('empresa')->id, 'tienda_id' => $request->attributes->get('tienda')->id, 'user_id' => $request->user()->id]; }
    protected function findScoped(Request $request, int $id): Compra { return Compra::where('tenant_id', $request->attributes->get('tenant')->id)->where('empresa_id', $request->attributes->get('empresa')->id)->where('tienda_id', $request->attributes->get('tienda')->id)->findOrFail($id); }
}
