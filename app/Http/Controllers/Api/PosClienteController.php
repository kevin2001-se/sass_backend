<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PosClienteResource;
use App\Services\PosClienteService;
use Illuminate\Http\Request;

class PosClienteController extends Controller
{
    public function __construct(private readonly PosClienteService $service)
    {
    }

    public function buscar(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $empresa = $request->attributes->get('empresa');

        $clientes = $this->service->buscar([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
        ], $request->query('q'), $request->integer('limit', 20));

        return PosClienteResource::collection($clientes);
    }
}