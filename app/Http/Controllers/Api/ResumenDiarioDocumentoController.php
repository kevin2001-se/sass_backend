<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResumenDiarioResource;
use App\Services\ResumenDiarioDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResumenDiarioDocumentoController extends Controller
{
    public function __construct(private readonly ResumenDiarioDocumentoService $service)
    {
    }

    public function generarPdfA4(Request $request, int $id)
    {
        try {
            $resumen = $this->service->generarPdfA4($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar el PDF del resumen diario.');
        }

        return response()->json([
            'success' => true,
            'message' => 'PDF generado correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function pdfA4(Request $request, int $id)
    {
        return $this->service->descargarPdfA4($id, $this->scope($request));
    }

    public function xml(Request $request, int $id)
    {
        return $this->service->descargarXml($id, $this->scope($request));
    }

    public function cdr(Request $request, int $id)
    {
        return $this->service->descargarCdr($id, $this->scope($request));
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