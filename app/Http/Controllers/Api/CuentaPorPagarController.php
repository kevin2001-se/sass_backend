<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagarCuentaPorPagarRequest;
use App\Http\Resources\CuentaPorPagarResource;
use App\Models\CuentaPorPagar;
use App\Services\CuentaPorPagarService;
use Illuminate\Http\Request;

class CuentaPorPagarController extends Controller
{
    public function __construct(private readonly CuentaPorPagarService $service) {}

    public function index(Request $request)
    {
        $cuentas = CuentaPorPagar::with(['proveedor', 'compra'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->orderBy('fecha_vencimiento')->paginate($request->integer('per_page', 15));
        return CuentaPorPagarResource::collection($cuentas);
    }

    public function show(Request $request, int $cuenta)
    {
        return new CuentaPorPagarResource($this->findScoped($request, $cuenta)->load(['proveedor', 'compra', 'pagos']));
    }

    public function pagar(PagarCuentaPorPagarRequest $request, int $cuenta)
    {
        $cuenta = $this->service->registrarPago($cuenta, array_merge($request->validated(), ['tenant_id' => $request->attributes->get('tenant')->id, 'empresa_id' => $request->attributes->get('empresa')->id, 'tienda_id' => $request->attributes->get('tienda')->id, 'user_id' => $request->user()->id]));
        return new CuentaPorPagarResource($cuenta);
    }

    protected function findScoped(Request $request, int $id): CuentaPorPagar
    {
        return CuentaPorPagar::where('tenant_id', $request->attributes->get('tenant')->id)->where('empresa_id', $request->attributes->get('empresa')->id)->where('tienda_id', $request->attributes->get('tienda')->id)->findOrFail($id);
    }
}
