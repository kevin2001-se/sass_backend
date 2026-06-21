<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularPagoClienteRequest;
use App\Http\Requests\PagarCuentaPorCobrarRequest;
use App\Http\Resources\CuentaPorCobrarPagoResource;
use App\Models\CuentaPorCobrarPago;
use App\Services\CuentaPorCobrarService;
use Illuminate\Http\Request;

class PagoClienteController extends Controller
{
    public function __construct(private readonly CuentaPorCobrarService $service)
    {
    }

    public function index(Request $request)
    {
        $pagos = CuentaPorCobrarPago::with(['cuentaPorCobrar.cliente', 'cuentaPorCobrar.venta', 'user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('cuenta_por_cobrar_id'), fn ($query) => $query->where('cuenta_por_cobrar_id', $request->integer('cuenta_por_cobrar_id')))
            ->when($request->filled('metodo_pago'), fn ($query) => $query->where('metodo_pago', $request->input('metodo_pago')))
            ->when($request->filled('cliente_id'), fn ($query) => $query->whereHas('cuentaPorCobrar', fn ($cuenta) => $cuenta->where('cliente_id', $request->integer('cliente_id'))))
            ->when($request->filled('fecha_inicio'), fn ($query) => $query->whereDate('fecha_pago', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($query) => $query->whereDate('fecha_pago', '<=', $request->input('fecha_fin')))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CuentaPorCobrarPagoResource::collection($pagos);
    }

    public function store(PagarCuentaPorCobrarRequest $request)
    {
        $cuenta = $this->service->registrarPago($request->integer('cuenta_por_cobrar_id'), array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]));

        $pago = $cuenta->pagos()->latest('id')->first();

        return new CuentaPorCobrarPagoResource($pago->load(['cuentaPorCobrar.cliente', 'cuentaPorCobrar.venta']));
    }

    public function show(Request $request, int $id)
    {
        return new CuentaPorCobrarPagoResource($this->findScoped($request, $id)->load(['cuentaPorCobrar.cliente', 'cuentaPorCobrar.venta', 'user']));
    }

    public function anular(AnularPagoClienteRequest $request, int $id)
    {
        $pago = $this->service->anularPago($id, array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]));

        return new CuentaPorCobrarPagoResource($pago->load(['cuentaPorCobrar.cliente', 'cuentaPorCobrar.venta', 'user']));
    }

    protected function findScoped(Request $request, int $id): CuentaPorCobrarPago
    {
        return CuentaPorCobrarPago::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }
}