<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularGuiaRemisionRequest;
use App\Http\Requests\StoreGuiaDesdeCompraRequest;
use App\Http\Requests\StoreGuiaDesdeVentaRequest;
use App\Http\Requests\StoreGuiaRemisionRequest;
use App\Http\Requests\UpdateGuiaRemisionRequest;
use App\Http\Resources\GuiaRemisionResource;
use App\Models\GuiaRemision;
use App\Services\GuiaRemisionService as BaseGuiaRemisionService;
use App\Services\Sunat\GuiaRemisionService as SunatGuiaRemisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuiaRemisionController extends Controller
{
    public function __construct(
        private readonly BaseGuiaRemisionService $service,
        private readonly SunatGuiaRemisionService $sunatService,
    ) {
    }

    public function index(Request $request)
    {
        return GuiaRemisionResource::collection(
            $this->service->listar(array_merge($request->only([
                'fecha_inicio',
                'fecha_fin',
                'estado',
                'motivo_traslado_codigo',
                'modalidad_transporte',
                'numero',
                'destinatario',
                'per_page',
            ]), $this->scope($request)))
        );
    }

    public function store(StoreGuiaRemisionRequest $request)
    {
        try {
            $guia = $this->service->crear(array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo registrar la guia de remision.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Guia de remision registrada correctamente.',
            'data' => new GuiaRemisionResource($guia),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        return new GuiaRemisionResource($this->service->obtener($id, $this->scope($request)));
    }

    public function update(UpdateGuiaRemisionRequest $request, int $id)
    {
        try {
            $guia = $this->service->actualizar(
                $this->findScoped($request, $id),
                array_merge($request->validated(), $this->scope($request))
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo actualizar la guia de remision.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Guia de remision actualizada correctamente.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }

    public function anular(AnularGuiaRemisionRequest $request, int $id)
    {
        try {
            $guia = $this->service->anular($this->findScoped($request, $id), $request->input('motivo'), $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo anular la guia de remision.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Guia de remision anulada correctamente.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }

    public function registrar(Request $request, int $id)
    {
        try {
            $guia = $this->service->registrar($this->findScoped($request, $id), $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo registrar la guia de remision.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Guia de remision registrada correctamente. Ya puede enviarla a SUNAT.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }
    public function desdeVenta(StoreGuiaDesdeVentaRequest $request, int $ventaId)
    {
        try {
            $guia = $this->sunatService->crearDesdeVenta($ventaId, array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo registrar la guia desde venta.');
        }

        return response()->json(['success' => true, 'message' => 'Guia de remision registrada desde venta correctamente.', 'data' => new GuiaRemisionResource($guia)], 201);
    }

    public function desdeCompra(StoreGuiaDesdeCompraRequest $request, int $compraId)
    {
        try {
            $guia = $this->sunatService->crearDesdeCompra($compraId, array_merge($request->validated(), $this->scope($request)));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo registrar la guia desde compra.');
        }

        return response()->json(['success' => true, 'message' => 'Guia de remision registrada desde compra correctamente.', 'data' => new GuiaRemisionResource($guia)], 201);
    }

    public function enviar(Request $request, int $id)
    {
        try {
            $guia = $this->sunatService->enviarGuiaConGreenter($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo enviar la guia a SUNAT.');
        }

        return response()->json([
            'success' => true,
            'message' => $guia->comprobanteElectronico?->estado_sunat === 'ACEPTADO'
                ? 'Guia de remision enviada a SUNAT correctamente.'
                : 'Guia de remision enviada a SUNAT con observaciones.',
            'data' => new GuiaRemisionResource($guia),
        ]);
    }

    public function reenviar(Request $request, int $id)
    {
        try {
            $guia = $this->sunatService->reenviarGuia($id, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->errorResponse($e, 'No se pudo reenviar la guia a SUNAT.');
        }

        return response()->json(['success' => true, 'message' => 'Guia de remision reenviada a SUNAT.', 'data' => new GuiaRemisionResource($guia)]);
    }

    public function xml(Request $request, int $id)
    {
        $guia = $this->findScoped($request, $id)->load('comprobanteElectronico');

        return $this->downloadPrivate($guia->comprobanteElectronico?->xml_path, ($guia->numero_completo ?: $guia->numero_guia).'.xml');
    }

    public function cdr(Request $request, int $id)
    {
        $guia = $this->findScoped($request, $id)->load('comprobanteElectronico');

        return $this->downloadPrivate($guia->comprobanteElectronico?->cdr_path, 'R-'.($guia->numero_completo ?: $guia->numero_guia).'.zip');
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

        return response()->json(['success' => false, 'message' => $message, 'error' => $errors->first() ?: $e->getMessage()], 422);
    }
}
