<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotaCreditoResource;
use App\Models\NotaCredito;
use App\Services\NotaCreditoSunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class NotaCreditoSunatController extends Controller
{
    public function __construct(private readonly NotaCreditoSunatService $service)
    {
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $nota = $this->service->enviar($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar la nota de credito a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $nota->estado_sunat === NotaCredito::SUNAT_ACEPTADO
                ? 'Nota de credito enviada correctamente.'
                : 'Nota de credito enviada con observaciones.',
            'data' => new NotaCreditoResource($nota),
        ]);
    }

    public function reenviar(Request $request, int $id)
    {
        try {
            $nota = $this->service->reenviar($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar la nota de credito a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $nota->estado_sunat === NotaCredito::SUNAT_ACEPTADO
                ? 'Nota de credito reenviada correctamente.'
                : 'Nota de credito reenviada con observaciones.',
            'data' => new NotaCreditoResource($nota),
        ]);
    }

    public function xml(Request $request, int $id)
    {
        $nota = $this->findScoped($request, $id);

        return $this->downloadPrivate($nota->xml_path, $nota->numero_completo.'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $nota = $this->findScoped($request, $id);

        return $this->downloadPrivate($nota->cdr_path, 'R-'.$nota->numero_completo.'.zip');
    }

    protected function findScoped(Request $request, int $id): NotaCredito
    {
        return NotaCredito::where('tenant_id', $request->attributes->get('tenant')->id)
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

    protected function downloadPrivate(?string $path, string $name)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => ['El archivo solicitado no existe.']]);
        }

        return Storage::disk('local')->download($path, $name);
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
