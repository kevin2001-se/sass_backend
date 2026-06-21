<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularComunicacionBajaRequest;
use App\Http\Requests\GenerarComunicacionBajaRequest;
use App\Http\Resources\ComprobanteElectronicoResource;
use App\Http\Resources\ComunicacionBajaResource;
use App\Models\ComunicacionBaja;
use App\Services\ComunicacionBajaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComunicacionBajaController extends Controller
{
    public function __construct(private readonly ComunicacionBajaService $service)
    {
    }

    public function index(Request $request)
    {
        return ComunicacionBajaResource::collection(
            $this->service->listar(array_merge($request->all(), $this->scope($request)))
        );
    }

    public function generar(GenerarComunicacionBajaRequest $request)
    {
        try {
            $comunicacion = $this->service->generar(array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar la comunicacion de baja.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja generada correctamente.',
            'data' => new ComunicacionBajaResource($comunicacion),
        ], 201);
    }

    public function show(int $id)
    {
        return new ComunicacionBajaResource($this->service->obtener($id));
    }

    public function anular(AnularComunicacionBajaRequest $request, int $id)
    {
        try {
            $comunicacion = $this->service->anular($this->findScoped($request, $id), $request->validated('motivo'));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo anular la comunicacion de baja.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja anulada correctamente.',
            'data' => new ComunicacionBajaResource($comunicacion),
        ]);
    }

    public function documentosPendientes(Request $request)
    {
        $request->validate([
            'fecha_baja' => ['nullable', 'date'],
        ]);

        $fecha = Carbon::parse($request->query('fecha_baja', now()->toDateString()))->startOfDay();

        return ComprobanteElectronicoResource::collection($this->service->obtenerDocumentosPendientes($fecha));
    }

    protected function findScoped(Request $request, int $id): ComunicacionBaja
    {
        return ComunicacionBaja::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }

    protected function scope(Request $request): array
    {
        return [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()?->id,
        ];
    }

    protected function errorResponse(ValidationException $e, string $message)
    {
        $errors = collect($e->errors())->flatten();

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $errors->first() ?: $e->getMessage(),
        ], 422);
    }
}
