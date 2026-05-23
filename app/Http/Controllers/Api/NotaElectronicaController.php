<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularNotaRequest;
use App\Http\Requests\StoreNotaCreditoRequest;
use App\Http\Requests\StoreNotaDebitoRequest;
use App\Http\Resources\NotaElectronicaResource;
use App\Models\NotaElectronica;
use App\Services\NotaElectronicaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotaElectronicaController extends Controller
{
    public function __construct(private readonly NotaElectronicaService $service)
    {
    }

    public function index(Request $request)
    {
        $notas = NotaElectronica::with(['venta.cliente', 'comprobanteElectronico'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('tipo_nota'), fn ($query) => $query->where('tipo_nota', $request->input('tipo_nota')))
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->input('estado')))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return NotaElectronicaResource::collection($notas);
    }

    public function credito(StoreNotaCreditoRequest $request)
    {
        try {
            $nota = $this->service->crearNotaCredito($this->payload($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e);
        }

        return (new NotaElectronicaResource($nota))->response()->setStatusCode(201);
    }

    public function debito(StoreNotaDebitoRequest $request)
    {
        try {
            $nota = $this->service->crearNotaDebito($this->payload($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e);
        }

        return (new NotaElectronicaResource($nota))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $nota)
    {
        return new NotaElectronicaResource($this->findScoped($request, $nota)->load(['detalles', 'venta.cliente', 'comprobanteReferencia', 'comprobanteElectronico']));
    }

    public function anular(AnularNotaRequest $request, int $nota)
    {
        $nota = $this->service->anularNota($nota, $request->validated('motivo'), $this->scope($request));

        return new NotaElectronicaResource($nota);
    }

    public function reenviar(Request $request, int $nota)
    {
        try {
            $nota = $this->service->reenviarNota($nota, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e);
        }

        return new NotaElectronicaResource($nota);
    }

    protected function payload(Request $request): array
    {
        return array_merge($request->validated(), $this->scope($request), [
            'user_id' => $request->user()->id,
        ]);
    }

    protected function scope(Request $request): array
    {
        return [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
        ];
    }

    protected function findScoped(Request $request, int $id): NotaElectronica
    {
        return NotaElectronica::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }

    protected function sunatErrorResponse(ValidationException $e)
    {
        return response()->json([
            'success' => false,
            'message' => 'No se pudo procesar la nota electrÃ³nica.',
            'error' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
        ], 422);
    }
}
