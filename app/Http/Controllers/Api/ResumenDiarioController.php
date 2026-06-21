<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularResumenDiarioRequest;
use App\Http\Requests\ConsultarTicketResumenRequest;
use App\Http\Requests\GenerarResumenDiarioRequest;
use App\Http\Resources\ResumenDiarioResource;
use App\Models\ResumenDiario;
use App\Services\ResumenDiarioService as ResumenDiarioBaseService;
use App\Services\Sunat\ResumenDiarioService as ResumenDiarioSunatService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ResumenDiarioController extends Controller
{
    public function __construct(
        private readonly ResumenDiarioBaseService $service,
        private readonly ResumenDiarioSunatService $sunatService
    ) {
    }

    public function index(Request $request)
    {
        return ResumenDiarioResource::collection(
            $this->service->listar(array_merge($request->all(), $this->scope($request)))
        );
    }

    public function documentosDisponibles(Request $request)
    {
        $validated = $request->validate([
            'fecha_resumen' => ['required', 'date'],
        ]);

        $documentos = $this->service->obtenerDocumentosParaResumen(
            Carbon::parse($validated['fecha_resumen'])
        );

        return response()->json([
            'success' => true,
            'data' => $documentos->values(),
        ]);
    }
    public function generar(GenerarResumenDiarioRequest $request)
    {
        try {
            $resumen = $this->service->generar(array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo generar el resumen diario.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario generado correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        return new ResumenDiarioResource($this->service->obtener($id));
    }

    public function anular(AnularResumenDiarioRequest $request, int $id)
    {
        try {
            $resumen = $this->service->anular($this->findScoped($request, $id), $request->validated('motivo'));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo anular el resumen diario.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario anulado correctamente.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $resumen = $this->sunatService->enviarResumen($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar el resumen diario a SUNAT.');
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
            $resumen = $this->sunatService->consultarTicket($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo consultar el ticket SUNAT.');
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
            $resumen = $this->sunatService->reenviarResumen($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar el resumen diario a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen diario reenviado a SUNAT.',
            'data' => new ResumenDiarioResource($resumen),
        ]);
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

