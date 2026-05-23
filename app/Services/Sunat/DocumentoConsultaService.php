<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Services\UserTiendaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentoConsultaService
{
    public function __construct(private readonly UserTiendaService $userTiendaService)
    {
    }

    public function buscarPorNumero(array $filtros, Request $request)
    {
        return $this->queryDocumentos($filtros, $request)
            ->when($filtros['serie'] ?? null, fn ($query, $serie) => $query->where('serie', $serie))
            ->when($filtros['numero'] ?? null, fn ($query, $numero) => $query->where('numero_comprobante', 'like', '%'.$numero.'%'))
            ->paginate($request->integer('per_page', 15));
    }

    public function buscarPorCliente(array $filtros, Request $request)
    {
        return $this->queryDocumentos($filtros, $request)
            ->when($filtros['cliente_id'] ?? null, function ($query, $clienteId) {
                $query->whereHas('venta', fn ($subquery) => $subquery->where('cliente_id', $clienteId))
                    ->orWhereHas('notaElectronica.venta', fn ($subquery) => $subquery->where('cliente_id', $clienteId))
                    ->orWhereHas('guiaRemision', fn ($subquery) => $subquery->where('cliente_id', $clienteId));
            })
            ->paginate($request->integer('per_page', 15));
    }

    public function buscarPorFecha(array $filtros, Request $request)
    {
        return $this->queryDocumentos($filtros, $request)->paginate($request->integer('per_page', 15));
    }

    public function obtenerDetalle(int $comprobanteId, Request $request): array
    {
        return app(DocumentoPdfService::class)->obtenerDataDocumento($this->findScoped($comprobanteId, $request));
    }

    public function obtenerXml(int $comprobanteId, Request $request)
    {
        $comprobante = $this->findScoped($comprobanteId, $request);

        return $this->download($comprobante->xml_path, $comprobante->numero_comprobante.'.xml');
    }

    public function obtenerCdr(int $comprobanteId, Request $request)
    {
        $comprobante = $this->findScoped($comprobanteId, $request);

        return $this->download($comprobante->cdr_path, 'R-'.$comprobante->numero_comprobante.'.zip');
    }

    public function obtenerPdf(int $comprobanteId, string $formato, Request $request)
    {
        $comprobante = $this->findScoped($comprobanteId, $request);
        $path = match ($formato) {
            'A4' => $comprobante->pdf_a4_path,
            'TICKET_80' => $comprobante->ticket_80_path,
            'TICKET_58' => $comprobante->ticket_58_path,
            default => null,
        };

        return $this->download($path, $comprobante->numero_comprobante.'-'.$formato.'.pdf');
    }

    public function findScoped(int $comprobanteId, Request $request): ComprobanteElectronico
    {
        return $this->baseQuery($request)->findOrFail($comprobanteId);
    }

    public function queryDocumentos(array $filtros, Request $request): Builder
    {
        return $this->baseQuery($request)
            ->when($filtros['fecha_inicio'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_emision', '>=', $fecha))
            ->when($filtros['fecha_fin'] ?? null, fn ($query, $fecha) => $query->whereDate('fecha_emision', '<=', $fecha))
            ->when($filtros['tipo_comprobante'] ?? null, fn ($query, $tipo) => $query->where('tipo_comprobante', $tipo))
            ->when($filtros['serie'] ?? null, fn ($query, $serie) => $query->where('serie', $serie))
            ->when($filtros['numero'] ?? null, fn ($query, $numero) => $query->where('numero_comprobante', 'like', '%'.$numero.'%'))
            ->when($filtros['estado_sunat'] ?? null, fn ($query, $estado) => $query->where('estado_sunat', $estado))
            ->when($filtros['cliente_id'] ?? null, function ($query, $clienteId) {
                $query->where(function ($subquery) use ($clienteId) {
                    $subquery->whereHas('venta', fn ($q) => $q->where('cliente_id', $clienteId))
                        ->orWhereHas('notaElectronica.venta', fn ($q) => $q->where('cliente_id', $clienteId))
                        ->orWhereHas('guiaRemision', fn ($q) => $q->where('cliente_id', $clienteId));
                });
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');
    }

    protected function baseQuery(Request $request): Builder
    {
        $user = $request->user();
        $tiendas = $this->tiendasPermitidas($request);

        return ComprobanteElectronico::with([
            'empresa',
            'tienda',
            'venta.cliente',
            'venta.detalles',
            'venta.pagos',
            'notaElectronica.venta.cliente',
            'notaElectronica.detalles',
            'guiaRemision.cliente',
            'guiaRemision.proveedor',
            'guiaRemision.detalles',
        ])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->whereIn('tienda_id', $tiendas)
            ->whereIn('tipo_comprobante', ['FACTURA', 'BOLETA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'GUIA_REMISION']);
    }

    protected function tiendasPermitidas(Request $request): array
    {
        $user = $request->user();
        $tiendaId = $request->integer('tienda_id');
        $ids = $this->userTiendaService->obtenerTiendasUsuario($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $global = $user->role?->permissions()->whereIn('name', ['documentos.global', 'sunat.documentos.global', 'reportes.global'])->exists();

        if ($global && $tiendaId) {
            return [$tiendaId];
        }

        if ($tiendaId && ! in_array($tiendaId, $ids, true)) {
            throw ValidationException::withMessages(['tienda_id' => ['No tiene acceso a la tienda solicitada.']]);
        }

        if ($tiendaId) {
            return [$tiendaId];
        }

        return $request->attributes->get('tienda') ? [(int) $request->attributes->get('tienda')->id] : $ids;
    }

    protected function download(?string $path, string $name)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => ['El archivo solicitado no existe.']]);
        }

        return Storage::disk('local')->download($path, $name);
    }
}
