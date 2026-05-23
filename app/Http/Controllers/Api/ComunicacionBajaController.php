<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarTicketBajaRequest;
use App\Http\Requests\GenerarComunicacionBajaRequest;
use App\Http\Resources\ComunicacionBajaResource;
use App\Models\ComunicacionBaja;
use App\Services\Sunat\ComunicacionBajaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ComunicacionBajaController extends Controller
{
    public function __construct(private readonly ComunicacionBajaService $service)
    {
    }

    public function index(Request $request)
    {
        $bajas = ComunicacionBaja::withCount('detalles')
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('fecha_baja'), fn ($query) => $query->whereDate('fecha_baja', $request->date('fecha_baja')))
            ->when($request->filled('fecha_envio'), fn ($query) => $query->whereDate('fecha_envio', $request->date('fecha_envio')))
            ->when($request->filled('estado_sunat'), fn ($query) => $query->where('estado_sunat', $request->input('estado_sunat')))
            ->orderByDesc('fecha_envio')
            ->orderByDesc('correlativo')
            ->paginate($request->integer('per_page', 15));

        return ComunicacionBajaResource::collection($bajas);
    }

    public function generar(GenerarComunicacionBajaRequest $request)
    {
        try {
            $baja = $this->service->generarBaja(array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo generar la comunicacion de baja.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja generada correctamente.',
            'data' => new ComunicacionBajaResource($baja),
        ], 201);
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $baja = $this->service->enviarBaja($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo enviar la comunicacion de baja a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja enviada a SUNAT. Consulte el ticket para obtener el CDR.',
            'data' => new ComunicacionBajaResource($baja),
        ]);
    }

    public function consultarTicket(ConsultarTicketBajaRequest $request, int $id)
    {
        try {
            $baja = $this->service->consultarTicket($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo consultar el ticket SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $baja->estado_sunat === ComunicacionBaja::ACEPTADO
                ? 'Comunicacion de baja aceptada por SUNAT.'
                : 'Consulta de ticket procesada.',
            'data' => new ComunicacionBajaResource($baja),
        ]);
    }

    public function reenviar(Request $request, int $id)
    {
        try {
            $baja = $this->service->reenviarBaja($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo reenviar la comunicacion de baja a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Comunicacion de baja reenviada a SUNAT.',
            'data' => new ComunicacionBajaResource($baja),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return new ComunicacionBajaResource($this->findScoped($request, $id)->load(['detalles.comprobanteElectronico'])->loadCount('detalles'));
    }

    public function xml(Request $request, int $id)
    {
        $baja = $this->findScoped($request, $id);

        return $this->downloadPrivate($baja->xml_path, $baja->identificador.'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $baja = $this->findScoped($request, $id);

        return $this->downloadPrivate($baja->cdr_path, 'R-'.$baja->identificador.'.zip');
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
        ];
    }

    protected function downloadPrivate(?string $path, string $name)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => ['El archivo solicitado no existe.']]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    protected function sunatErrorResponse(ValidationException $e, string $message)
    {
        $errors = collect($e->errors())->flatten();

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $errors->first() ?: $e->getMessage(),
        ], 422);
    }
}
