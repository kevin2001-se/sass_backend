<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\ComunicacionBaja;
use App\Models\ComunicacionBajaDetalle;
use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComunicacionBajaService
{
    public function listar(array $filters): LengthAwarePaginator
    {
        $scope = $this->scope($filters);

        return ComunicacionBaja::with(['tienda', 'creadoPor'])
            ->withCount('detalles')
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->when($filters['fecha_inicio'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_baja', '>=', $fecha))
            ->when($filters['fecha_fin'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_baja', '<=', $fecha))
            ->when($filters['fecha_baja'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_baja', $fecha))
            ->when($filters['estado'] ?? null, fn (Builder $query, $estado) => $query->where('estado', $estado))
            ->when($filters['estado_sunat'] ?? null, fn (Builder $query, $estado) => $query->where('estado_sunat', $estado))
            ->when($filters['identificador'] ?? null, fn (Builder $query, $identificador) => $query->where('identificador', 'ilike', '%'.$identificador.'%'))
            ->orderByDesc('fecha_baja')
            ->orderByDesc('correlativo')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function obtener(int $id): ComunicacionBaja
    {
        $scope = $this->scope();

        return ComunicacionBaja::with(['detalles.comprobante.venta.cliente', 'tienda', 'creadoPor', 'anuladoPor'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function generar(array $data): ComunicacionBaja
    {
        $scope = $this->scope($data);

        return DB::transaction(function () use ($data, $scope) {
            $fecha = Carbon::parse($data['fecha_baja'])->startOfDay();
            $documentos = $this->obtenerDocumentosPendientesInterno($fecha, $scope, $data['comprobantes'] ?? []);

            if ($documentos->isEmpty()) {
                throw ValidationException::withMessages(['comprobantes' => ['No hay comprobantes pendientes de baja para generar la comunicacion.']]);
            }

            $solicitados = collect($data['comprobantes'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            if ($solicitados->isNotEmpty() && $documentos->count() !== $solicitados->count()) {
                throw ValidationException::withMessages(['comprobantes' => ['Uno o mas comprobantes no estan pendientes de baja, no fueron aceptados por SUNAT o ya estan en una comunicacion activa.']]);
            }

            $correlativo = $this->siguienteCorrelativo($scope, $fecha);
            $comunicacion = ComunicacionBaja::create([
                'tenant_id' => $scope['tenant_id'],
                'empresa_id' => $scope['empresa_id'],
                'tienda_id' => $scope['tienda_id'],
                'fecha_baja' => $fecha->toDateString(),
                'fecha_envio' => $fecha->toDateString(),
                'identificador' => $this->formatearIdentificador($fecha, $correlativo),
                'correlativo' => $correlativo,
                'estado' => ComunicacionBaja::REGISTRADA,
                'estado_sunat' => ComunicacionBaja::PENDIENTE,
                'total_documentos' => $documentos->count(),
                'observacion' => $data['observacion'] ?? null,
                'created_by' => $data['user_id'] ?? Auth::id(),
            ]);

            foreach ($documentos as $documento) {
                ComunicacionBajaDetalle::create([
                    'tenant_id' => $comunicacion->tenant_id,
                    'empresa_id' => $comunicacion->empresa_id,
                    'tienda_id' => $comunicacion->tienda_id,
                    'comunicacion_baja_id' => $comunicacion->id,
                    'comprobante_id' => $documento->id,
                    'comprobante_electronico_id' => $documento->id,
                    'tipo_documento' => $documento->tipo_comprobante,
                    'serie' => $documento->serie ?: $this->serieDesdeNumero($documento->numero_comprobante),
                    'correlativo' => (int) ($documento->correlativo ?: $this->correlativoDesdeNumero($documento->numero_comprobante)),
                    'numero_comprobante' => $documento->numero_comprobante,
                    'numero_completo' => $documento->numero_comprobante,
                    'motivo_baja' => $documento->motivo_baja ?: 'Baja de comprobante',
                ]);

                $documento->update(['estado_baja' => ComprobanteElectronico::BAJA_EN_BAJA]);
            }

            return $comunicacion->load(['detalles.comprobante', 'tienda', 'creadoPor'])->loadCount('detalles');
        });
    }

    public function anular(ComunicacionBaja $comunicacion, string $motivo): ComunicacionBaja
    {
        $scope = $this->scope();
        $this->validarScope($comunicacion, $scope);

        if ($comunicacion->estado === ComunicacionBaja::ANULADA) {
            throw ValidationException::withMessages(['estado' => ['La comunicacion de baja ya esta anulada.']]);
        }

        if (! in_array($comunicacion->estado_sunat, [ComunicacionBaja::PENDIENTE, ComunicacionBaja::ERROR], true)) {
            throw ValidationException::withMessages(['estado_sunat' => ['Solo se puede anular antes del envio aceptado por SUNAT o cuando quedo en ERROR sin ticket.']]);
        }

        return DB::transaction(function () use ($comunicacion, $motivo) {
            $comunicacion->load('detalles.comprobante');

            foreach ($comunicacion->detalles as $detalle) {
                if ($detalle->comprobante && $detalle->comprobante->estado_baja === ComprobanteElectronico::BAJA_EN_BAJA) {
                    $detalle->comprobante->update(['estado_baja' => ComprobanteElectronico::BAJA_PENDIENTE]);
                }
            }

            $comunicacion->update([
                'estado' => ComunicacionBaja::ANULADA,
                'motivo_anulacion' => $motivo,
                'anulado_by' => Auth::id(),
                'anulado_at' => now(),
            ]);

            return $comunicacion->refresh()->load(['detalles.comprobante', 'tienda', 'creadoPor', 'anuladoPor'])->loadCount('detalles');
        });
    }

    public function obtenerDocumentosPendientes(Carbon $fecha, array $ids = []): Collection
    {
        return $this->obtenerDocumentosPendientesInterno($fecha, $this->scope(), $ids);
    }

    public function generarIdentificador(Carbon $fecha): string
    {
        return $this->formatearIdentificador($fecha, $this->siguienteCorrelativo($this->scope(), $fecha));
    }

    protected function obtenerDocumentosPendientesInterno(Carbon $fecha, array $scope, array $ids = []): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        return ComprobanteElectronico::with(['venta.cliente'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->whereIn('tipo_comprobante', [Venta::FACTURA, 'NOTA_CREDITO', 'NOTA_DEBITO'])
            ->where('estado_sunat', ComprobanteElectronico::ACEPTADO)
            ->where('estado_baja', ComprobanteElectronico::BAJA_PENDIENTE)
            ->whereDate('fecha_solicitud_baja', '<=', $fecha->toDateString())
            ->when($ids->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $ids->all()))
            ->whereNotIn('id', $this->documentosEnComunicacionActiva($scope))
            ->orderBy('serie')
            ->orderBy('correlativo')
            ->lockForUpdate()
            ->get()
            ->filter(fn (ComprobanteElectronico $documento) => $this->correspondeComunicacionBaja($documento))
            ->values();
    }

    protected function correspondeComunicacionBaja(ComprobanteElectronico $documento): bool
    {
        if ($documento->tipo_comprobante === Venta::FACTURA) {
            return true;
        }

        if ($documento->tipo_comprobante === 'NOTA_CREDITO') {
            return NotaCredito::with('comprobante')
                ->where(function (Builder $query) use ($documento) {
                    $query->where('id', $documento->documento_origen_id)
                        ->orWhere('id', $documento->nota_electronica_id)
                        ->orWhere('numero_completo', $documento->numero_comprobante);
                })
                ->first()?->comprobante?->tipo_comprobante === Venta::FACTURA;
        }

        if ($documento->tipo_comprobante === 'NOTA_DEBITO') {
            return NotaDebito::with('comprobante')
                ->where(function (Builder $query) use ($documento) {
                    $query->where('id', $documento->documento_origen_id)
                        ->orWhere('id', $documento->nota_electronica_id)
                        ->orWhere('numero_completo', $documento->numero_comprobante);
                })
                ->first()?->comprobante?->tipo_comprobante === Venta::FACTURA;
        }

        return false;
    }
    protected function documentosEnComunicacionActiva(array $scope): array
    {
        return ComunicacionBajaDetalle::whereHas('comunicacionBaja', function (Builder $query) use ($scope) {
            $query->where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->where('estado', ComunicacionBaja::REGISTRADA)
                ->whereIn('estado_sunat', [ComunicacionBaja::PENDIENTE, ComunicacionBaja::ENVIADO, ComunicacionBaja::ACEPTADO]);
        })->pluck('comprobante_id')->map(fn ($id) => (int) $id)->all();
    }

    protected function siguienteCorrelativo(array $scope, Carbon $fecha): int
    {
        $ultimo = ComunicacionBaja::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->whereDate('fecha_baja', $fecha->toDateString())
            ->lockForUpdate()
            ->orderByDesc('correlativo')
            ->value('correlativo');

        return ((int) $ultimo) + 1;
    }

    protected function formatearIdentificador(Carbon $fecha, int $correlativo): string
    {
        return 'RA-'.$fecha->format('Ymd').'-'.str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }

    protected function serieDesdeNumero(?string $numero): string
    {
        return $numero && str_contains($numero, '-') ? explode('-', $numero, 2)[0] : '';
    }

    protected function correlativoDesdeNumero(?string $numero): int
    {
        return $numero && str_contains($numero, '-') ? (int) ltrim(explode('-', $numero, 2)[1], '0') : 0;
    }

    protected function validarScope(ComunicacionBaja $comunicacion, array $scope): void
    {
        if ($comunicacion->tenant_id !== $scope['tenant_id'] || $comunicacion->empresa_id !== $scope['empresa_id'] || $comunicacion->tienda_id !== $scope['tienda_id']) {
            abort(404);
        }
    }

    protected function scope(array $override = []): array
    {
        $request = request();

        return [
            'tenant_id' => (int) ($override['tenant_id'] ?? $request->attributes->get('tenant')?->id),
            'empresa_id' => (int) ($override['empresa_id'] ?? $request->attributes->get('empresa')?->id),
            'tienda_id' => (int) ($override['tienda_id'] ?? $request->attributes->get('tienda')?->id),
        ];
    }
}

