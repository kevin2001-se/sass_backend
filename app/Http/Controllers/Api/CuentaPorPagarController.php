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
        return CuentaPorPagarResource::collection($this->service->listar($request));
    }

    public function show(Request $request, int $cuenta)
    {
        return new CuentaPorPagarResource($this->service->obtener($cuenta, $this->scope($request)));
    }

    public function pagar(PagarCuentaPorPagarRequest $request, int $cuenta)
    {
        $cuenta = $this->service->registrarPago($cuenta, array_merge($request->validated(), $this->scope($request)));
        return new CuentaPorPagarResource($cuenta);
    }

    protected function scope(Request $request): array
    {
        return [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ];
    }
}