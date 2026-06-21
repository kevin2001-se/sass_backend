<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\ResumenDiario;
use App\Models\ResumenDiarioDetalle;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResumenDiarioService
{
    public function listar(array $filters): LengthAwarePaginator
    {
        $scope = $this->scope($filters);

        return ResumenDiario::with(['tienda', 'creadoPor'])
            ->withCount('detalles')
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->when($filters['fecha_inicio'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_resumen', '>=', $fecha))
            ->when($filters['fecha_fin'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_resumen', '<=', $fecha))
            ->when($filters['fecha_resumen'] ?? null, fn (Builder $query, $fecha) => $query->whereDate('fecha_resumen', $fecha))
            ->when($filters['estado'] ?? null, fn (Builder $query, $estado) => $query->where('estado', $estado))
            ->when($filters['estado_sunat'] ?? null, fn (Builder $query, $estado) => $query->where('estado_sunat', $estado))
            ->when($filters['identificador'] ?? null, fn (Builder $query, $identificador) => $query->where('identificador', 'ilike', '%'.$identificador.'%'))
            ->orderByDesc('fecha_resumen')
            ->orderByDesc('correlativo')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function obtener(int $id): ResumenDiario
    {
        $scope = $this->scope();

        return ResumenDiario::with(['detalles', 'tienda', 'creadoPor', 'anuladoPor'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function generar(array $data): ResumenDiario
    {
        $scope = $this->scope($data);

        return DB::transaction(function () use ($data, $scope) {
            $fecha = Carbon::parse($data['fecha_resumen'])->startOfDay();
            $documentos = $this->obtenerDocumentosParaResumenInterno($fecha, $scope, $data);

            if ($documentos->isEmpty()) {
                throw ValidationException::withMessages([
                    'documentos' => ['No hay boletas, notas de credito o notas de debito de boleta para resumir en la fecha indicada.'],
                ]);
            }

            $correlativo = $this->siguienteCorrelativo($scope, $fecha);
            $totales = $this->calcularTotales($documentos);

            $resumen = ResumenDiario::create([
                'tenant_id' => $scope['tenant_id'],
                'empresa_id' => $scope['empresa_id'],
                'tienda_id' => $scope['tienda_id'],
                'fecha_resumen' => $fecha->toDateString(),
                'fecha_envio' => $fecha->toDateString(),
                'correlativo' => $correlativo,
                'identificador' => $this->formatearIdentificador($fecha, $correlativo),
                'estado' => ResumenDiario::REGISTRADO,
                'estado_sunat' => ResumenDiario::PENDIENTE,
                'total_documentos' => $totales['total_documentos'],
                'total_boletas' => $totales['total_boletas'],
                'total_notas_credito' => $totales['total_notas_credito'],
                'total_notas_debito' => $totales['total_notas_debito'],
                'monto_total' => $totales['monto_total'],
                'observacion' => $data['observacion'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($documentos as $documento) {
                ResumenDiarioDetalle::create(array_merge([
                    'tenant_id' => $resumen->tenant_id,
                    'empresa_id' => $resumen->empresa_id,
                    'tienda_id' => $resumen->tienda_id,
                    'resumen_diario_id' => $resumen->id,
                    'estado_item' => $documento['accion'] === ResumenDiarioDetalle::ACCION_BAJA ? ResumenDiarioDetalle::ANULAR : ResumenDiarioDetalle::ADICIONAR,
                ], $documento));
            }

            $this->marcarBajasEnResumen($resumen);

            return $resumen->load(['detalles', 'tienda', 'creadoPor'])->loadCount('detalles');
        });
    }

    public function anular(ResumenDiario $resumen, string $motivo): ResumenDiario
    {
        $scope = $this->scope();

        if ($resumen->tenant_id !== $scope['tenant_id'] || $resumen->empresa_id !== $scope['empresa_id'] || $resumen->tienda_id !== $scope['tienda_id']) {
            abort(404);
        }

        if ($resumen->estado === ResumenDiario::ANULADO) {
            throw ValidationException::withMessages(['estado' => ['El resumen diario ya esta anulado.']]);
        }

        if ($resumen->estado_sunat === ResumenDiario::ACEPTADO) {
            throw ValidationException::withMessages(['estado_sunat' => ['No se puede anular internamente un resumen diario aceptado por SUNAT.']]);
        }

        return DB::transaction(function () use ($resumen, $motivo) {
            $this->devolverBajasPendientes($resumen);

            $resumen->update([
                'estado' => ResumenDiario::ANULADO,
                'motivo_anulacion' => $motivo,
                'anulado_by' => Auth::id(),
                'anulado_at' => now(),
            ]);

            return $resumen->refresh()->load(['detalles', 'tienda', 'creadoPor', 'anuladoPor'])->loadCount('detalles');
        });
    }

    public function obtenerDocumentosParaResumen(Carbon $fecha): Collection
    {
        return $this->obtenerDocumentosParaResumenInterno($fecha, $this->scope(), []);
    }

    public function generarIdentificador(Carbon $fecha): string
    {
        return $this->formatearIdentificador($fecha, $this->siguienteCorrelativo($this->scope(), $fecha));
    }

    protected function obtenerDocumentosParaResumenInterno(Carbon $fecha, array $scope, array $filters): Collection
    {
        $documentos = collect();
        $incluirBoletas = (bool) ($filters['incluir_boletas'] ?? true);
        $incluirNotasCredito = (bool) ($filters['incluir_notas_credito'] ?? true);
        $incluirNotasDebito = (bool) ($filters['incluir_notas_debito'] ?? true);

        if ($incluirBoletas) {
            $documentos = $documentos->merge($this->boletasParaResumen($fecha, $scope));
        }
        if ($incluirNotasCredito) {
            $documentos = $documentos->merge($this->notasCreditoParaResumen($fecha, $scope));
        }
        if ($incluirNotasDebito) {
            $documentos = $documentos->merge($this->notasDebitoParaResumen($fecha, $scope));
        }

        return $documentos->unique(fn ($item) => $item['tipo_documento'].'-'.$item['documento_id'].'-'.$item['accion'])
            ->sortBy(['serie', 'correlativo'])
            ->values();
    }

    protected function boletasParaResumen(Carbon $fecha, array $scope): Collection
    {
        [$inicio, $fin] = $this->rangoDiaLocal($fecha);

        $boletasConComprobante = ComprobanteElectronico::with(['venta.cliente'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('tipo_comprobante', Venta::BOLETA)
            ->whereHas('venta', fn (Builder $query) => $query->where('estado', '!=', Venta::ANULADA))
            ->where(function (Builder $query) use ($inicio, $fin, $fecha) {
                $query->where(function (Builder $normal) use ($inicio, $fin) {
                    $normal->whereBetween('fecha_emision', [$inicio, $fin])
                        ->whereIn('estado_sunat', [ComprobanteElectronico::PENDIENTE, ComprobanteElectronico::ACEPTADO])
                        ->where(function (Builder $estadoBaja) {
                            $estadoBaja->whereNull('estado_baja')->orWhere('estado_baja', ComprobanteElectronico::BAJA_SIN_BAJA);
                        });
                })->orWhere(function (Builder $baja) use ($fecha) {
                    $baja->where('estado_sunat', ComprobanteElectronico::ACEPTADO)
                        ->where('estado_baja', ComprobanteElectronico::BAJA_PENDIENTE)
                        ->whereDate('fecha_solicitud_baja', '<=', $fecha->toDateString());
                });
            })
            ->whereNotIn('id', $this->documentosEnResumenActivo($scope, ResumenDiarioDetalle::BOLETA))
            ->get()
            ->toBase()
            ->map(fn (ComprobanteElectronico $documento) => $this->mapComprobante($documento));

        $boletasSinComprobante = Venta::with(['cliente', 'comprobanteElectronico'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('tipo_comprobante', Venta::BOLETA)
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->where('estado', Venta::REGISTRADA)
            ->whereDoesntHave('comprobanteElectronico')
            ->get()
            ->toBase()
            ->map(fn (Venta $venta) => $this->mapComprobante($this->crearComprobantePendienteDesdeVenta($venta)));

        return $boletasConComprobante->merge($boletasSinComprobante);
    }

    protected function notasCreditoParaResumen(Carbon $fecha, array $scope): Collection
    {
        [$inicio, $fin] = $this->rangoDiaLocal($fecha);

        $normales = NotaCredito::with(['venta.cliente', 'comprobante'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->whereBetween('created_at', [$inicio, $fin])
            ->where('estado', NotaCredito::REGISTRADA)
            ->whereIn('estado_sunat', [NotaCredito::SUNAT_PENDIENTE, NotaCredito::SUNAT_ACEPTADO])
            ->whereHas('venta', fn (Builder $query) => $query->where('estado', '!=', Venta::ANULADA))
            ->whereHas('comprobante', fn (Builder $query) => $query->where('tipo_comprobante', Venta::BOLETA))
            ->whereNotIn('id', $this->documentosEnResumenActivo($scope, ResumenDiarioDetalle::NOTA_CREDITO))
            ->get()
            ->map(fn (NotaCredito $nota) => $this->mapNotaCredito($nota));

        $bajas = ComprobanteElectronico::with(['venta.cliente'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('tipo_comprobante', 'NOTA_CREDITO')
            ->where('estado_sunat', ComprobanteElectronico::ACEPTADO)
            ->where('estado_baja', ComprobanteElectronico::BAJA_PENDIENTE)
            ->whereDate('fecha_solicitud_baja', '<=', $fecha->toDateString())
            ->whereNotIn('id', $this->documentosEnResumenActivo($scope, ResumenDiarioDetalle::NOTA_CREDITO, true))
            ->get()
            ->map(function (ComprobanteElectronico $comprobante) {
                $nota = $this->notaCreditoDesdeComprobante($comprobante);
                return $nota && $nota->comprobante?->tipo_comprobante === Venta::BOLETA ? $this->mapNotaCredito($nota, $comprobante) : null;
            })
            ->filter();

        return $normales->merge($bajas);
    }

    protected function notasDebitoParaResumen(Carbon $fecha, array $scope): Collection
    {
        [$inicio, $fin] = $this->rangoDiaLocal($fecha);

        $normales = NotaDebito::with(['venta.cliente', 'comprobante'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->whereBetween('created_at', [$inicio, $fin])
            ->where('estado', NotaDebito::REGISTRADA)
            ->whereIn('estado_sunat', [NotaDebito::SUNAT_PENDIENTE, NotaDebito::SUNAT_ACEPTADO])
            ->whereHas('venta', fn (Builder $query) => $query->where('estado', '!=', Venta::ANULADA))
            ->whereHas('comprobante', fn (Builder $query) => $query->where('tipo_comprobante', Venta::BOLETA))
            ->whereNotIn('id', $this->documentosEnResumenActivo($scope, ResumenDiarioDetalle::NOTA_DEBITO))
            ->get()
            ->map(fn (NotaDebito $nota) => $this->mapNotaDebito($nota));

        $bajas = ComprobanteElectronico::with(['venta.cliente'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('tipo_comprobante', 'NOTA_DEBITO')
            ->where('estado_sunat', ComprobanteElectronico::ACEPTADO)
            ->where('estado_baja', ComprobanteElectronico::BAJA_PENDIENTE)
            ->whereDate('fecha_solicitud_baja', '<=', $fecha->toDateString())
            ->whereNotIn('id', $this->documentosEnResumenActivo($scope, ResumenDiarioDetalle::NOTA_DEBITO, true))
            ->get()
            ->map(function (ComprobanteElectronico $comprobante) {
                $nota = $this->notaDebitoDesdeComprobante($comprobante);
                return $nota && $nota->comprobante?->tipo_comprobante === Venta::BOLETA ? $this->mapNotaDebito($nota, $comprobante) : null;
            })
            ->filter();

        return $normales->merge($bajas);
    }

    protected function documentosEnResumenActivo(array $scope, string $tipo, bool $porComprobanteElectronico = false): array
    {
        $columna = $porComprobanteElectronico ? 'comprobante_electronico_id' : 'documento_id';

        return ResumenDiarioDetalle::where('tipo_documento', $tipo)
            ->whereHas('resumenDiario', function (Builder $query) use ($scope) {
                $query->where('tenant_id', $scope['tenant_id'])
                    ->where('empresa_id', $scope['empresa_id'])
                    ->where('tienda_id', $scope['tienda_id'])
                    ->whereIn('estado_sunat', [ResumenDiario::PENDIENTE, ResumenDiario::ENVIADO, ResumenDiario::ACEPTADO])
                    ->where('estado', '!=', ResumenDiario::ANULADO);
            })
            ->pluck($columna)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function mapComprobante(ComprobanteElectronico $documento): array
    {
        $venta = $documento->venta;
        $cliente = $venta?->cliente;
        [$serie, $correlativo] = $this->serieCorrelativo($documento->serie, $documento->correlativo, $documento->numero_comprobante);
        $esBaja = $documento->estado_baja === ComprobanteElectronico::BAJA_PENDIENTE;

        return [
            'documento_id' => $documento->id,
            'comprobante_electronico_id' => $documento->id,
            'tipo_documento' => ResumenDiarioDetalle::BOLETA,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero_comprobante' => $documento->numero_comprobante,
            'numero_completo' => $documento->numero_comprobante,
            'cliente_tipo_documento' => $cliente?->tipo_documento,
            'cliente_numero_documento' => $cliente?->numero_documento,
            'cliente_nombre' => $this->nombreCliente($cliente),
            'subtotal' => round((float) ($venta?->subtotal ?? 0), 2),
            'total_igv' => round((float) ($venta?->total_igv ?? 0), 2),
            'total' => round((float) ($venta?->total ?? 0), 2),
            'estado_documento' => $documento->estado_sunat,
            'accion' => $esBaja ? ResumenDiarioDetalle::ACCION_BAJA : ResumenDiarioDetalle::ACCION_ALTA,
            'estado_baja' => $documento->estado_baja,
            'motivo_baja' => $documento->motivo_baja,
        ];
    }

    protected function crearComprobantePendienteDesdeVenta(Venta $venta): ComprobanteElectronico
    {
        return ComprobanteElectronico::firstOrCreate(
            [
                'empresa_id' => $venta->empresa_id,
                'venta_id' => $venta->id,
            ],
            [
                'tenant_id' => $venta->tenant_id,
                'tienda_id' => $venta->tienda_id,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'serie' => $venta->serie,
                'correlativo' => $venta->correlativo,
                'numero_comprobante' => $venta->numero_comprobante,
                'fecha_emision' => $venta->fecha_emision,
                'moneda' => \parametro('moneda_default', 'PEN'),
                'estado_sunat' => ComprobanteElectronico::PENDIENTE,
            ]
        )->load('venta.cliente');
    }

    protected function mapNotaCredito(NotaCredito $nota, ?ComprobanteElectronico $comprobanteElectronico = null): array
    {
        [$serie, $correlativo] = $this->serieCorrelativo($nota->serie, $nota->correlativo, $nota->numero_completo);
        $cliente = $nota->venta?->cliente;
        $esBaja = $comprobanteElectronico?->estado_baja === ComprobanteElectronico::BAJA_PENDIENTE;

        return [
            'documento_id' => $nota->id,
            'comprobante_electronico_id' => $comprobanteElectronico?->id,
            'tipo_documento' => ResumenDiarioDetalle::NOTA_CREDITO,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero_comprobante' => $nota->numero_completo,
            'numero_completo' => $nota->numero_completo,
            'cliente_tipo_documento' => $cliente?->tipo_documento,
            'cliente_numero_documento' => $cliente?->numero_documento,
            'cliente_nombre' => $this->nombreCliente($cliente),
            'subtotal' => round((float) $nota->subtotal, 2),
            'total_igv' => round((float) $nota->total_igv, 2),
            'total' => round((float) $nota->total, 2),
            'estado_documento' => $comprobanteElectronico?->estado_sunat ?? $nota->estado_sunat,
            'accion' => $esBaja ? ResumenDiarioDetalle::ACCION_BAJA : ResumenDiarioDetalle::ACCION_ALTA,
            'estado_baja' => $comprobanteElectronico?->estado_baja,
            'motivo_baja' => $comprobanteElectronico?->motivo_baja,
        ];
    }

    protected function mapNotaDebito(NotaDebito $nota, ?ComprobanteElectronico $comprobanteElectronico = null): array
    {
        [$serie, $correlativo] = $this->serieCorrelativo($nota->serie, $nota->correlativo, $nota->numero_completo);
        $cliente = $nota->venta?->cliente;
        $esBaja = $comprobanteElectronico?->estado_baja === ComprobanteElectronico::BAJA_PENDIENTE;

        return [
            'documento_id' => $nota->id,
            'comprobante_electronico_id' => $comprobanteElectronico?->id,
            'tipo_documento' => ResumenDiarioDetalle::NOTA_DEBITO,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero_comprobante' => $nota->numero_completo,
            'numero_completo' => $nota->numero_completo,
            'cliente_tipo_documento' => $cliente?->tipo_documento,
            'cliente_numero_documento' => $cliente?->numero_documento,
            'cliente_nombre' => $this->nombreCliente($cliente),
            'subtotal' => round((float) $nota->subtotal, 2),
            'total_igv' => round((float) $nota->total_igv, 2),
            'total' => round((float) $nota->total, 2),
            'estado_documento' => $comprobanteElectronico?->estado_sunat ?? $nota->estado_sunat,
            'accion' => $esBaja ? ResumenDiarioDetalle::ACCION_BAJA : ResumenDiarioDetalle::ACCION_ALTA,
            'estado_baja' => $comprobanteElectronico?->estado_baja,
            'motivo_baja' => $comprobanteElectronico?->motivo_baja,
        ];
    }

    protected function notaCreditoDesdeComprobante(ComprobanteElectronico $comprobante): ?NotaCredito
    {
        return NotaCredito::with(['venta.cliente', 'comprobante'])
            ->where('tenant_id', $comprobante->tenant_id)
            ->where('empresa_id', $comprobante->empresa_id)
            ->where('tienda_id', $comprobante->tienda_id)
            ->where(function (Builder $query) use ($comprobante) {
                $query->where('id', $comprobante->documento_origen_id)
                    ->orWhere('id', $comprobante->nota_electronica_id)
                    ->orWhere('numero_completo', $comprobante->numero_comprobante);
            })
            ->first();
    }

    protected function notaDebitoDesdeComprobante(ComprobanteElectronico $comprobante): ?NotaDebito
    {
        return NotaDebito::with(['venta.cliente', 'comprobante'])
            ->where('tenant_id', $comprobante->tenant_id)
            ->where('empresa_id', $comprobante->empresa_id)
            ->where('tienda_id', $comprobante->tienda_id)
            ->where(function (Builder $query) use ($comprobante) {
                $query->where('id', $comprobante->documento_origen_id)
                    ->orWhere('id', $comprobante->nota_electronica_id)
                    ->orWhere('numero_completo', $comprobante->numero_comprobante);
            })
            ->first();
    }

    protected function marcarBajasEnResumen(ResumenDiario $resumen): void
    {
        $resumen->load('detalles');

        foreach ($resumen->detalles->where('accion', ResumenDiarioDetalle::ACCION_BAJA) as $detalle) {
            $this->actualizarComprobanteBajaDetalle($detalle, ComprobanteElectronico::BAJA_EN_BAJA);
        }
    }

    protected function devolverBajasPendientes(ResumenDiario $resumen): void
    {
        $resumen->load('detalles');

        foreach ($resumen->detalles->where('accion', ResumenDiarioDetalle::ACCION_BAJA) as $detalle) {
            $this->actualizarComprobanteBajaDetalle($detalle, ComprobanteElectronico::BAJA_PENDIENTE);
        }
    }

    protected function actualizarComprobanteBajaDetalle(ResumenDiarioDetalle $detalle, string $estadoBaja): void
    {
        if ($detalle->comprobante_electronico_id) {
            ComprobanteElectronico::whereKey($detalle->comprobante_electronico_id)->update(['estado_baja' => $estadoBaja]);
        }
    }

    protected function calcularTotales(Collection $documentos): array
    {
        return [
            'total_documentos' => $documentos->count(),
            'total_boletas' => $documentos->where('tipo_documento', ResumenDiarioDetalle::BOLETA)->count(),
            'total_notas_credito' => $documentos->where('tipo_documento', ResumenDiarioDetalle::NOTA_CREDITO)->count(),
            'total_notas_debito' => $documentos->where('tipo_documento', ResumenDiarioDetalle::NOTA_DEBITO)->count(),
            'monto_total' => round((float) $documentos->sum('total'), 2),
        ];
    }

    protected function rangoDiaLocal(Carbon $fecha): array
    {
        $timezone = config('sunat.timezone', 'America/Lima');
        $fechaLocal = Carbon::parse($fecha->toDateString(), $timezone);

        return [
            $fechaLocal->copy()->startOfDay()->utc(),
            $fechaLocal->copy()->endOfDay()->utc(),
        ];
    }

    protected function siguienteCorrelativo(array $scope, Carbon $fecha): int
    {
        $ultimo = ResumenDiario::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->whereDate('fecha_resumen', $fecha->toDateString())
            ->lockForUpdate()
            ->orderByDesc('correlativo')
            ->value('correlativo');

        return ((int) $ultimo) + 1;
    }

    protected function formatearIdentificador(Carbon $fecha, int $correlativo): string
    {
        return 'RC-'.$fecha->format('Ymd').'-'.str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }

    protected function serieCorrelativo(?string $serie, mixed $correlativo, ?string $numero): array
    {
        if ((! $serie || ! $correlativo) && $numero && str_contains($numero, '-')) {
            [$serieNumero, $correlativoNumero] = explode('-', $numero, 2);
            return [$serie ?: $serieNumero, (int) ltrim($correlativo ?: $correlativoNumero, '0')];
        }

        return [$serie ?: '', (int) $correlativo];
    }

    protected function nombreCliente($cliente): ?string
    {
        if (! $cliente) {
            return null;
        }

        return $cliente->razon_social ?: $cliente->nombres;
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