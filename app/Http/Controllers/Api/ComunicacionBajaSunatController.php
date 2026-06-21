<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComunicacionBajaResource;
use App\Models\ComunicacionBaja;
use App\Services\ComunicacionBajaSunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ComunicacionBajaSunatController extends Controller
{
    public function __construct(private readonly ComunicacionBajaSunatService $service)
    {
    }

    public function enviar(int $id)
    {
        try {
            $comunicacion = $this->service->enviar($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar la comunicacion de baja a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja enviada a SUNAT correctamente.',
            'data' => new ComunicacionBajaResource($comunicacion),
        ]);
    }

    public function reenviar(int $id)
    {
        try {
            $comunicacion = $this->service->reenviar($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar la comunicacion de baja a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja reenviada a SUNAT correctamente.',
            'data' => new ComunicacionBajaResource($comunicacion),
        ]);
    }

    public function consultarTicket(int $id)
    {
        try {
            $comunicacion = $this->service->consultarTicket($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo consultar el ticket SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket consultado correctamente.',
            'data' => new ComunicacionBajaResource($comunicacion),
        ]);
    }

    public function xml(Request $request, int $id)
    {
        $comunicacion = $this->findScoped($request, $id);

        if (! $comunicacion->xml_path || ! Storage::disk('local')->exists($comunicacion->xml_path)) {
            throw ValidationException::withMessages(['xml' => ['El XML de la comunicacion de baja no esta disponible.']]);
        }

        return Storage::disk('local')->download($comunicacion->xml_path, $comunicacion->identificador.'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $comunicacion = $this->findScoped($request, $id);

        if (! $comunicacion->cdr_path || ! Storage::disk('local')->exists($comunicacion->cdr_path)) {
            throw ValidationException::withMessages(['cdr' => ['El CDR de la comunicacion de baja no esta disponible.']]);
        }

        return Storage::disk('local')->download($comunicacion->cdr_path, 'R-'.$comunicacion->identificador.'.zip');
    }

    protected function findScoped(Request $request, int $id): ComunicacionBaja
    {
        return ComunicacionBaja::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
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
