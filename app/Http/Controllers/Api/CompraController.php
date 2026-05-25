<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularCompraRequest;
use App\Http\Requests\StoreCompraRequest;
use App\Http\Resources\CompraResource;
use App\Services\CompraService;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compraService) {}

    public function index(Request $request)
    {
        return CompraResource::collection($this->compraService->listar($request));
    }

    public function store(StoreCompraRequest $request)
    {
        $compra = $this->compraService->registrar($this->payload($request));

        return (new CompraResource($compra))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $compra)
    {
        return new CompraResource($this->compraService->obtener($compra, $this->scope($request)));
    }

    public function anular(AnularCompraRequest $request, int $compra)
    {
        return new CompraResource($this->compraService->anularCompra($compra, $request->validated('motivo'), $this->scope($request)));
    }

    protected function payload(Request $request): array
    {
        return array_merge($request->validated(), $this->scope($request));
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
