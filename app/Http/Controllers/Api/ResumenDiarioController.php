<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarTicketResumenRequest;
use App\Http\Requests\GenerarResumenDiarioRequest;
use App\Http\Resources\ResumenDiarioResource;
use App\Models\ResumenDiario;
use App\Services\Sunat\ResumenDiarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResumenDiarioController extends Controller
{
    public function __construct(private readonly ResumenDiarioService $service)
    {
    }

    public function index(Request $request)
    {
        $resumenes = ResumenDiario::withCount('detalles')
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('fecha_resumen'), fn ($query) => $query->whereDate('fecha_resumen', $request->date('fecha_resumen')))
            ->when($request->filled('fecha_envio'), fn ($query) => $query->whereDate('fecha_envio', $request->date('fecha_envio')))
            ->when($request->filled('estado_sunat'), fn ($query) => $query->where('estado_sunat', $request->input('estado_sunat')))
            ->orderByDesc('fecha_envio')
            ->orderByDesc('correlativo')
            ->paginate($request->integer('per_page', 15));

        return ResumenDiarioResource::collection($resumenes);
    }

    public function generar(GenerarResumenDiarioRequest $request)
    {
        try {
            $resumen = $this->service->generarResumen(array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo generar el resumen diario.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario generado correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ], 201);
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $resumen = $this->service->enviarResumen($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo enviar el resumen diario a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario enviado a SUNAT. Consulte el ticket para obtener el CDR.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function consultarTicket(ConsultarTicketResumenRequest $request, int $id)
    {
        try {
            $resumen = $this->service->consultarTicket($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo consultar el ticket SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $resumen->estado_sunat === ResumenDiario::ACEPTADO
                ? 'Resumen diario aceptado por SUNAT.'
                : 'Consulta de ticket procesada.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function reenviar(Request $request, int $id)
    {
        try {
            $resumen = $this->service->reenviarResumen($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e, 'No se pudo reenviar el resumen diario a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario reenviado a SUNAT.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return new ResumenDiarioResource($this->findScoped($request, $id)->load(['detalles.comprobanteElectronico'])->loadCount('detalles'));
    }

    public function xml(Request $request, int $id)
    {
        $resumen = $this->findScoped($request, $id);

        return $this->downloadPrivate($resumen->xml_path, $resumen->identificador.'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $resumen = $this->findScoped($request, $id);

        return $this->downloadPrivate($resumen->cdr_path, 'R-'.$resumen->identificador.'.zip');
    }

    protected function findScoped(Request $request, int $id): ResumenDiario
    {
        return ResumenDiario::where('tenant_id', $request->attributes->get('tenant')->id)
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
