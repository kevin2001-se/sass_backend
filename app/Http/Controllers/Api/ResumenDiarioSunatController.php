<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResumenDiarioResource;
use App\Models\ResumenDiario;
use App\Services\ResumenDiarioSunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResumenDiarioSunatController extends Controller
{
    public function __construct(private readonly ResumenDiarioSunatService $service)
    {
    }

    public function enviar(int $id)
    {
        try {
            $resumen = $this->service->enviar($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar el resumen diario a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario enviado a SUNAT correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function reenviar(int $id)
    {
        try {
            $resumen = $this->service->reenviar($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar el resumen diario a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario reenviado a SUNAT correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function consultarTicket(int $id)
    {
        try {
            $resumen = $this->service->consultarTicket($id);
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo consultar el ticket SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket consultado correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function xml(Request $request, int $id)
    {
        $resumen = $this->findScoped($request, $id);

        if (! $resumen->xml_path || ! Storage::disk('local')->exists($resumen->xml_path)) {
            throw ValidationException::withMessages(['xml' => ['El XML del resumen diario no esta disponible.']]);
        }

        return Storage::disk('local')->download($resumen->xml_path, $resumen->identificador.'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $resumen = $this->findScoped($request, $id);

        if (! $resumen->cdr_path || ! Storage::disk('local')->exists($resumen->cdr_path)) {
            throw ValidationException::withMessages(['cdr' => ['El CDR del resumen diario no esta disponible.']]);
        }

        return Storage::disk('local')->download($resumen->cdr_path, 'R-'.$resumen->identificador.'.zip');
    }

    protected function findScoped(Request $request, int $id): ResumenDiario
    {
        return ResumenDiario::where('tenant_id', $request->attributes->get('tenant')->id)
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