<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuiaRemisionResource;
use App\Models\GuiaRemision;
use App\Services\GuiaRemisionSunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuiaRemisionSunatController extends Controller
{
    public function __construct(private readonly GuiaRemisionSunatService $service)
    {
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $guia = $this->service->enviar($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar la guia a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $guia->estado_sunat === GuiaRemision::SUNAT_ACEPTADO
                ? 'Guia enviada a SUNAT correctamente.'
                : 'Guia enviada a SUNAT con observaciones.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }

    public function reenviar(Request $request, int $id)
    {
        try {
            $guia = $this->service->reenviar($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar la guia a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $guia->estado_sunat === GuiaRemision::SUNAT_ACEPTADO
                ? 'Guia reenviada a SUNAT correctamente.'
                : 'Guia reenviada a SUNAT con observaciones.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }

    public function xml(Request $request, int $id)
    {
        $guia = $this->findScoped($request, $id);

        return $this->downloadPrivate($guia->xml_path, ($guia->numero_completo ?: $guia->numero_guia).'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $guia = $this->findScoped($request, $id);

        return $this->downloadPrivate($guia->cdr_path, 'R-'.($guia->numero_completo ?: $guia->numero_guia).'.zip');
    }

    protected function findScoped(Request $request, int $id): GuiaRemision
    {
        return GuiaRemision::where('tenant_id', $request->attributes->get('tenant')->id)
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