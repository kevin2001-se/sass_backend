<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitarBajaComprobanteRequest;
use App\Http\Resources\ComprobanteBajaHistorialResource;
use App\Http\Resources\ComprobanteElectronicoResource;
use App\Services\ComprobanteBajaService;
use Illuminate\Http\JsonResponse;

class ComprobanteBajaController extends Controller
{
    public function __construct(private readonly ComprobanteBajaService $service)
    {
    }

    public function solicitar(SolicitarBajaComprobanteRequest $request, int $id): JsonResponse
    {
        $scope = $this->scope($request);
        $comprobante = $this->service->solicitarBaja($id, $request->string('motivo_baja')->toString(), $scope);

        return response()->json([
            'success' => true,
            'message' => 'Baja interna solicitada correctamente.',
            'data' => new ComprobanteElectronicoResource($comprobante),
        ]);
    }

    public function historial($id): JsonResponse
    {
        $historial = $this->service->historial((int) $id, $this->scope(request()));

        return response()->json([
            'success' => true,
            'data' => ComprobanteBajaHistorialResource::collection($historial),
        ]);
    }

    protected function scope($request): array
    {
        return [
            'tenant_id' => (int) $request->attributes->get('tenant')->id,
            'empresa_id' => (int) $request->attributes->get('empresa')->id,
            'tienda_id' => (int) $request->attributes->get('tienda')->id,
            'user_id' => (int) $request->user()->id,
        ];
    }
}