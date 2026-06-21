<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagarCuentaPorCobrarRequest;
use App\Http\Resources\CuentaPorCobrarResource;
use App\Models\CuentaPorCobrar;
use App\Services\CuentaPorCobrarService;
use Illuminate\Http\Request;

class CuentaPorCobrarController extends Controller
{
    public function __construct(private readonly CuentaPorCobrarService $service)
    {
    }

    public function index(Request $request)
    {
        $this->service->marcarVencidas();

        $cuentas = CuentaPorCobrar::with(['cliente', 'venta'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->input('estado')))
            ->when($request->filled('cliente_id'), fn ($query) => $query->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('fecha_inicio'), fn ($query) => $query->whereDate('fecha_emision', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($query) => $query->whereDate('fecha_emision', '<=', $request->input('fecha_fin')))
            ->when($request->boolean('vencidas'), fn ($query) => $query->where('estado', CuentaPorCobrar::VENCIDA))
            ->orderBy('fecha_vencimiento')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CuentaPorCobrarResource::collection($cuentas);
    }

    public function show(Request $request, int $cuenta)
    {
        return new CuentaPorCobrarResource($this->findScoped($request, $cuenta)->load(['cliente', 'venta', 'pagos']));
    }

    public function pagar(PagarCuentaPorCobrarRequest $request, int $cuenta)
    {
        $cuenta = $this->service->registrarPago($cuenta, array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]));

        return new CuentaPorCobrarResource($cuenta);
    }

    public function cliente(Request $request, int $clienteId)
    {
        $cuentas = CuentaPorCobrar::with(['cliente', 'venta', 'pagos'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->where('cliente_id', $clienteId)
            ->orderBy('fecha_vencimiento')
            ->paginate($request->integer('per_page', 15));

        return CuentaPorCobrarResource::collection($cuentas);
    }

    public function vencidas(Request $request)
    {
        $this->service->marcarVencidas();

        $cuentas = CuentaPorCobrar::with(['cliente', 'venta'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->where('estado', CuentaPorCobrar::VENCIDA)
            ->orderBy('fecha_vencimiento')
            ->paginate($request->integer('per_page', 15));

        return CuentaPorCobrarResource::collection($cuentas);
    }

    protected function findScoped(Request $request, int $id): CuentaPorCobrar
    {
        return CuentaPorCobrar::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }
}
