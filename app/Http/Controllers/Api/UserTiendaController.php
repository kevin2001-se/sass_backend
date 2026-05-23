<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TiendaResource;
use App\Services\UserTiendaService;
use Illuminate\Http\Request;

class UserTiendaController extends Controller
{
    public function __construct(private readonly UserTiendaService $service)
    {
    }

    public function misTiendas(Request $request)
    {
        return TiendaResource::collection($this->service->obtenerTiendasUsuario($request->user()));
    }

    public function seleccionar(Request $request)
    {
        $data = $request->validate([
            'tienda_id' => ['required', 'integer'],
        ]);

        $tienda = $this->service->seleccionarTienda($request->user(), (int) $data['tienda_id']);

        return response()->json([
            'message' => 'Tienda activa seleccionada correctamente.',
            'tienda_activa' => new TiendaResource($tienda),
        ]);
    }
}
