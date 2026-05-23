<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Http\Requests\ReenviarComprobanteRequest;
use App\Http\Resources\ComprobanteElectronicoResource;
use App\Models\ComprobanteElectronico;
use App\Services\Sunat\ComprobanteElectronicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ComprobanteElectronicoController extends Controller
{
    public function __construct(private readonly ComprobanteElectronicoService $service)
    {
    }

    public function index(Request $request)
    {
        $comprobantes = ComprobanteElectronico::with('venta')
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado_sunat'), fn ($query) => $query->where('estado_sunat', $request->input('estado_sunat')))
            ->when($request->filled('tipo_comprobante'), fn ($query) => $query->where('tipo_comprobante', $request->input('tipo_comprobante')))
            ->when($request->filled('venta_id'), fn ($query) => $query->where('venta_id', $request->integer('venta_id')))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ComprobanteElectronicoResource::collection($comprobantes);
    }

    public function emitir(EmitirComprobanteRequest $request, int $ventaId)
    {
        try {
            $comprobante = $this->service->emitirDesdeVenta($ventaId, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => $comprobante->estado_sunat === ComprobanteElectronico::ACEPTADO
                ? 'Comprobante enviado a SUNAT correctamente.'
                : 'Comprobante enviado a SUNAT con observaciones.',
            'data' => new ComprobanteElectronicoResource($comprobante),
        ], 201);
    }

    public function reenviar(ReenviarComprobanteRequest $request, int $comprobante)
    {
        try {
            $comprobante = $this->service->reenviar($comprobante, $this->scope($request));
        } catch (ValidationException $e) {
            return $this->sunatErrorResponse($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Comprobante reenviado a SUNAT.',
            'data' => new ComprobanteElectronicoResource($comprobante),
        ]);
    }

    public function show(Request $request, int $comprobante)
    {
        return new ComprobanteElectronicoResource($this->findScoped($request, $comprobante));
    }

    public function xml(Request $request, int $comprobante)
    {
        $comprobante = $this->findScoped($request, $comprobante);

        return $this->downloadPrivate($comprobante->xml_path, $comprobante->numero_comprobante.'.xml');
    }

    public function cdr(Request $request, int $comprobante)
    {
        $comprobante = $this->findScoped($request, $comprobante);

        return $this->downloadPrivate($comprobante->cdr_path, 'R-'.$comprobante->numero_comprobante.'.zip');
    }

    protected function findScoped(Request $request, int $id): ComprobanteElectronico
    {
        return ComprobanteElectronico::where('tenant_id', $request->attributes->get('tenant')->id)
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
            throw ValidationException::withMessages([
                'archivo' => ['El archivo solicitado no existe.'],
            ]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    protected function sunatErrorResponse(ValidationException $e)
    {
        $errors = collect($e->errors())->flatten();

        return response()->json([
            'success' => false,
            'message' => 'No se pudo enviar el comprobante a SUNAT.',
            'error' => $errors->first() ?: $e->getMessage(),
        ], 422);
    }
}
