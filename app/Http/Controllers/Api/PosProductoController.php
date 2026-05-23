<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PosProductoResource;
use App\Services\PosProductoService;
use Illuminate\Http\Request;

class PosProductoController extends Controller
{
    public function __construct(private readonly PosProductoService $posProductoService)
    {
    }

    public function buscar(Request $request)
    {
        $productos = $this->posProductoService->buscar([
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
        ], $request->query('q'), min($request->integer('limit', 20), 50));

        return PosProductoResource::collection($productos);
    }
    public function rapidos(Request $request)
    {
        $productos = $this->posProductoService->rapidos([
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
        ], min($request->integer('limit', 30), 60));

        return PosProductoResource::collection($productos);
    }
}
