<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompraResource;
use App\Services\CompraDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompraDocumentoController extends Controller
{
    public function __construct(private readonly CompraDocumentoService $service) {}

    public function generarPdf(Request $request, int $id)
    {
        try {
            $compra = $this->service->generarPdf($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar el PDF de compra.');
        }

        return response()->json([
            'success' => true,
            'message' => 'PDF generado correctamente.',
            'data' => new CompraResource($compra),
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        return $this->service->descargarPdf($id, $this->scope($request));
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
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    }
}
