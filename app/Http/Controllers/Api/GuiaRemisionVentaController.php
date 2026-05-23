<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuiaDesdeVentaRequest;
use App\Http\Resources\GuiaRemisionResource;
use App\Models\Venta;
use App\Services\GuiaRemisionVentaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuiaRemisionVentaController extends Controller
{
    public function __construct(private readonly GuiaRemisionVentaService $service)
    {
    }

    public function crearDesdeVenta(StoreGuiaDesdeVentaRequest $request, Venta $venta)
    {
        try {
            $guia = $this->service->crearDesdeVenta($venta, array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo crear la guia desde venta.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Guia creada desde venta correctamente.',
            'data' => new GuiaRemisionResource($guia),
        ], 201);
    }

    public function data(Request $request, Venta $venta)
    {
        try {
            $data = $this->service->dataDesdeVenta($venta, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo preparar la data para guia desde venta.');
        }

        return response()->json(['success' => true, 'data' => $data]);
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
            'errors' => $e->errors(),
        ], 422);
    }
}