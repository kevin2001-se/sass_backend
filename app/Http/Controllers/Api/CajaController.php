<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AperturarCajaRequest;
use App\Http\Requests\CerrarCajaRequest;
use App\Http\Resources\ArqueoCajaResource;
use App\Http\Resources\CajaResource;
use App\Models\Caja;
use App\Services\CajaService;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function index(Request $request)
    {
        $cajas = Caja::with(['userApertura', 'userCierre'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->input('estado')))
            ->orderByDesc('fecha_apertura')
            ->paginate($request->integer('per_page', 15));

        return CajaResource::collection($cajas);
    }

    public function aperturar(AperturarCajaRequest $request)
    {
        $caja = $this->cajaService->aperturarCaja($this->payload($request));

        return (new CajaResource($caja))->response()->setStatusCode(201);
    }

    public function cerrar(CerrarCajaRequest $request, int $caja)
    {
        $resultado = $this->cajaService->cerrarCaja($caja, $this->payload($request));

        return response()->json([
            'data' => [
                'caja' => (new CajaResource($resultado['caja']))->resolve($request),
                'arqueo' => (new ArqueoCajaResource($resultado['arqueo']))->resolve($request),
            ],
        ]);
    }

    public function show(Request $request, int $caja)
    {
        return new CajaResource($this->findScoped($request, $caja)->load(['userApertura', 'userCierre', 'movimientos.user']));
    }

    public function abierta(Request $request)
    {
        $caja = Caja::with(['userApertura', 'movimientos.user'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->where('estado', Caja::ABIERTA)
            ->first();

        if (! $caja) {
            return response()->json(['message' => 'No hay caja abierta para esta tienda.'], 404);
        }

        return new CajaResource($caja);
    }

    public function arqueo(Request $request, int $caja)
    {
        $this->findScoped($request, $caja);

        return new ArqueoCajaResource($this->cajaService->generarArqueo($caja));
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

    protected function findScoped(Request $request, int $id): Caja
    {
        return Caja::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }
}
