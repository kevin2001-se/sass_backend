<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateParametroRequest;
use App\Http\Resources\ParametroResource;
use App\Services\ParametroService;

class ParametroController extends Controller
{
    public function __construct(private ParametroService $service) {}

    public function index()
    {
        $data = $this->service->listarAgrupados()
            ->map(fn ($items) => ParametroResource::collection($items)->resolve())
            ->all();

        return response()->json(['data' => $data]);
    }

    public function grupo(string $grupo)
    {
        return ParametroResource::collection($this->service->listarPorGrupo($grupo));
    }

    public function update(UpdateParametroRequest $request)
    {
        $actualizados = $this->service->actualizarMultiples($request->validated('parametros'));

        return response()->json([
            'success' => true,
            'message' => 'Parametros actualizados correctamente.',
            'data' => ParametroResource::collection($actualizados)->resolve(),
        ]);
    }
}