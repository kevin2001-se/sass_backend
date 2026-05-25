<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTiendaRequest;
use App\Http\Requests\UpdateTiendaRequest;
use App\Http\Resources\TiendaResource;
use App\Services\TiendaService;
use Illuminate\Http\Request;

class TiendaController extends Controller
{
    public function __construct(private TiendaService $service) {}

    public function index(Request $request)
    {
        return TiendaResource::collection($this->service->listar($request));
    }

    public function store(StoreTiendaRequest $request)
    {
        return (new TiendaResource($this->service->crear($request, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $tienda)
    {
        return new TiendaResource($this->service->findScoped($request, $tienda));
    }

    public function update(UpdateTiendaRequest $request, int $tienda)
    {
        $model = $this->service->findScoped($request, $tienda);
        return new TiendaResource($this->service->actualizar($model, $request->validated()));
    }

    public function destroy(Request $request, int $tienda)
    {
        $model = $this->service->findScoped($request, $tienda);
        $model->update(['estado' => false]);
        return response()->json(['message' => 'Tienda desactivada correctamente.']);
    }
}