<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotaCreditoResource;
use App\Services\NotaCreditoDocumentoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotaCreditoDocumentoController extends Controller
{
    public function __construct(private readonly NotaCreditoDocumentoService $service)
    {
    }

    public function generarPdfA4(Request $request, int $id)
    {
        try {
            $nota = $this->service->generarPdfA4($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar el PDF de la nota de credito.');
        }

        return response()->json([
            'success' => true,
            'message' => 'PDF generado correctamente.',
            'data' => new NotaCreditoResource($nota),
        ]);
    }

    public function generarTicket80(Request $request, int $id)
    {
        try {
            $nota = $this->service->generarTicket80($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar el ticket de la nota de credito.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket generado correctamente.',
            'data' => new NotaCreditoResource($nota),
        ]);
    }

    public function generarFormatos(Request $request, int $id)
    {
        try {
            $nota = $this->service->generarTodosFormatos($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudieron generar los formatos de la nota de credito.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Formatos generados correctamente.',
            'data' => new NotaCreditoResource($nota),
        ]);
    }

    public function pdfA4(Request $request, int $id)
    {
        return $this->service->descargarPdfA4($id, $this->scope($request));
    }

    public function ticket80(Request $request, int $id)
    {
        return $this->service->descargarTicket80($id, $this->scope($request));
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
        $errors = collect($e->errors())->flatten();

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $errors->first() ?: $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    }
}
