<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarEgresoCajaRequest;
use App\Http\Requests\RegistrarIngresoCajaRequest;
use App\Http\Resources\CajaMovimientoResource;
use App\Models\CajaMovimiento;
use App\Services\CajaService;
use Illuminate\Http\Request;

class CajaMovimientoController extends Controller
{
    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function index(Request $request)
    {
        $movimientos = CajaMovimiento::with(['caja', 'user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('caja_id'), fn ($query) => $query->where('caja_id', $request->integer('caja_id')))
            ->when($request->filled('tipo_movimiento'), fn ($query) => $query->where('tipo_movimiento', $request->input('tipo_movimiento')))
            ->when($request->filled('metodo_pago'), fn ($query) => $query->where('metodo_pago', $request->input('metodo_pago')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CajaMovimientoResource::collection($movimientos);
    }

    public function ingreso(RegistrarIngresoCajaRequest $request)
    {
        $movimiento = $this->cajaService->registrarIngreso($this->payload($request));

        return (new CajaMovimientoResource($movimiento))->response()->setStatusCode(201);
    }

    public function egreso(RegistrarEgresoCajaRequest $request)
    {
        $movimiento = $this->cajaService->registrarEgreso($this->payload($request));

        return (new CajaMovimientoResource($movimiento))->response()->setStatusCode(201);
    }

    protected function payload(Request $request): array
    {
        return array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]);
    }
}
