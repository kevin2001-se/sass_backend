<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductoConfiguracionRequest;
use App\Http\Resources\ProductoConfiguracionResource;
use App\Services\ProductoConfiguracionService;
use Illuminate\Http\Request;

class ProductoConfiguracionController extends Controller
{
    public function __construct(private readonly ProductoConfiguracionService $service)
    {
    }

    public function show(Request $request)
    {
        return new ProductoConfiguracionResource(
            $this->service->obtenerOcrear(
                $request->attributes->get('tenant')->id,
                $request->attributes->get('empresa')->id,
            )
        );
    }

    public function update(UpdateProductoConfiguracionRequest $request)
    {
        return new ProductoConfiguracionResource(
            $this->service->actualizar(
                $request->attributes->get('tenant')->id,
                $request->attributes->get('empresa')->id,
                $request->validated(),
            )
        );
    }
}
