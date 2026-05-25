<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Http\Resources\ProveedorResource;
use App\Services\ProveedorService;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct(private ProveedorService $service) {}

    public function index(Request $request)
    {
        return ProveedorResource::collection($this->service->listar($request));
    }

    public function store(StoreProveedorRequest $request)
    {
        return (new ProveedorResource($this->service->crear($request, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $proveedor)
    {
        return new ProveedorResource($this->service->findScoped($request, $proveedor));
    }

    public function update(UpdateProveedorRequest $request, int $proveedor)
    {
        $model = $this->service->findScoped($request, $proveedor);
        return new ProveedorResource($this->service->actualizar($model, $request->validated()));
    }

    public function destroy(Request $request, int $proveedor)
    {
        $this->service->findScoped($request, $proveedor)->update(['estado' => false]);
        return response()->json(['message' => 'Proveedor desactivado correctamente.']);
    }
}