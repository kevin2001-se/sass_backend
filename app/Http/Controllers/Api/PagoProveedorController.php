<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularPagoProveedorRequest;
use App\Http\Requests\StorePagoProveedorRequest;
use App\Http\Resources\PagoProveedorResource;
use App\Services\PagoProveedorService;
use Illuminate\Http\Request;

class PagoProveedorController extends Controller
{
    public function __construct(private readonly PagoProveedorService $service) {}

    public function index(Request $request)
    {
        return PagoProveedorResource::collection($this->service->listar($request));
    }

    public function store(StorePagoProveedorRequest $request)
    {
        $pago = $this->service->registrar(array_merge($request->validated(), $this->scope($request)));
        return (new PagoProveedorResource($pago))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $pago)
    {
        return new PagoProveedorResource($this->service->obtener($pago, $this->scope($request)));
    }

    public function anular(AnularPagoProveedorRequest $request, int $pago)
    {
        return new PagoProveedorResource($this->service->anular($pago, $request->validated('motivo'), $this->scope($request)));
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