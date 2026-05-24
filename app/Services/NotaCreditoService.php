<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\MotivoNotaCredito;
use App\Models\NotaCredito;
use App\Models\SerieComprobante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaCreditoService
{
    public function __construct(private readonly NotaCreditoEfectosService $efectosService)
    {
    }

    public function listar(array $filters, array $scope): LengthAwarePaginator
    {
        return NotaCredito::with(['venta.cliente', 'comprobante', 'motivo'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->when($filters['fecha_inicio'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('created_at', '>=', $fecha))
            ->when($filters['fecha_fin'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('created_at', '<=', $fecha))
            ->when($filters['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('estado', $estado))
            ->when($filters['numero'] ?? null, fn (Builder $query, string $numero) => $query->where('numero_completo', 'ILIKE', '%'.trim($numero).'%'))
            ->when($filters['comprobante_ref'] ?? null, function (Builder $query, string $numero) {
                $query->whereHas('comprobante', fn (Builder $subQuery) => $subQuery->where('numero_comprobante', 'ILIKE', '%'.trim($numero).'%'));
            })
            ->when($filters['cliente'] ?? null, function (Builder $query, string $cliente) {
                $term = '%'.trim($cliente).'%';
                $query->whereHas('venta.cliente', function (Builder $subQuery) use ($term) {
                    $subQuery->where('nombres', 'ILIKE', $term)
                        ->orWhere('razon_social', 'ILIKE', $term)
                        ->orWhere('numero_documento', 'ILIKE', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function obtener(int $id, array $scope): NotaCredito
    {
        return NotaCredito::with([
            'venta.cliente',
            'venta.detalles',
            'comprobante.venta.cliente',
            'motivo',
            'detalles.producto',
            'detalles.ventaDetalle',
            'cajaMovimiento',
        ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function crear(array $data, array $scope): NotaCredito
    {
        return DB::transaction(function () use ($data, $scope) {
            $comprobante = $this->validarComprobante($data['comprobante_id'], $scope);
            $venta = $comprobante->venta;
            $tipoNota = $data['tipo_nota'];
            $motivo = $this->validarMotivo($data['motivo_codigo']);
            $detalles = $tipoNota === NotaCredito::TOTAL
                ? $this->detallesTotales($venta)
                : $this->detallesParciales($venta, $data['detalles'] ?? []);
            $totales = $this->calcularTotales($detalles);
            $numero = $this->generarNumero($scope['tienda_id'], $comprobante->tipo_comprobante);

            $nota = NotaCredito::create([
                'tenant_id' => $scope['tenant_id'],
                'empresa_id' => $scope['empresa_id'],
                'tienda_id' => $scope['tienda_id'],
                'venta_id' => $venta->id,
                'comprobante_id' => $comprobante->id,
                'serie' => $numero['serie'],
                'correlativo' => $numero['correlativo'],
                'numero_completo' => $numero['numero_completo'],
                'motivo_codigo' => $motivo->codigo,
                'motivo_descripcion' => $data['motivo_descripcion'] ?? $motivo->descripcion,
                'tipo_nota' => $tipoNota,
                'afecta_stock' => $this->flagEfecto($data, 'afecta_stock', $motivo->codigo),
                'afecta_caja' => $this->flagEfecto($data, 'afecta_caja', $motivo->codigo),
                'subtotal' => $totales['subtotal'],
                'total_descuento' => $totales['descuento'],
                'total_igv' => $totales['igv'],
                'total' => $totales['total'],
                'observacion' => $data['observacion'] ?? null,
                'estado' => NotaCredito::REGISTRADA,
                'created_by' => $scope['user_id'],
            ]);

            $nota->detalles()->createMany($detalles);
            $this->efectosService->aplicarEfectos($nota, $data);

            return $this->obtener($nota->id, $scope);
        });
    }

    public function anular(NotaCredito $nota, string $motivo, array $scope): NotaCredito
    {
        return DB::transaction(function () use ($nota, $motivo, $scope) {
            $nota = NotaCredito::where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($nota->id);

            if ($nota->estado === NotaCredito::ANULADA) {
                throw ValidationException::withMessages(['nota_credito' => ['La nota de credito ya esta anulada.']]);
            }

            $nota->update([
                'anulado_by' => $scope['user_id'],
                'anulado_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            $this->efectosService->revertirEfectosSiAnula($nota);

            $nota->update([
                'estado' => NotaCredito::ANULADA,
                'observacion' => trim(($nota->observacion ? $nota->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);

            return $this->obtener($nota->id, $scope);
        });
    }

    public function aplicarEfectosPendientes(NotaCredito $nota, array $options, array $scope): NotaCredito
    {
        $nota = NotaCredito::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($nota->id);

        $this->efectosService->aplicarEfectos($nota, $options);

        return $this->obtener($nota->id, $scope);
    }

    public function generarNumero(int $tiendaId, ?string $tipoComprobanteReferencia = null): array
    {
        $prefijo = match ($tipoComprobanteReferencia) {
            Venta::BOLETA => 'B',
            Venta::FACTURA => 'F',
            default => null,
        };

        $query = SerieComprobante::where('tienda_id', $tiendaId)
            ->where('tipo_comprobante', 'NOTA_CREDITO')
            ->where('estado', true);

        if ($prefijo) {
            $query->where('serie', 'ILIKE', $prefijo.'%');
        }

        $serie = $query->orderBy('serie')->lockForUpdate()->first();

        if (! $serie) {
            $serieSugerida = $tipoComprobanteReferencia === Venta::BOLETA ? 'BC01' : 'FC01';
            $tipo = $tipoComprobanteReferencia ?: 'el comprobante referenciado';
            throw ValidationException::withMessages(['serie' => ["No existe serie activa {$serieSugerida} para NOTA_CREDITO de {$tipo} en la tienda activa."]]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [
            'serie' => $serie->serie,
            'correlativo' => $correlativo,
            'numero_completo' => $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        ];
    }

    protected function validarComprobante(int $comprobanteId, array $scope): ComprobanteElectronico
    {
        $comprobante = ComprobanteElectronico::with([
            'venta.detalles.presentacion.unidadMedida',
            'venta.detalles.producto',
            'venta.cliente',
        ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($comprobanteId);

        if (! in_array($comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_id' => ['La nota de credito solo aplica para BOLETA o FACTURA.']]);
        }

        if ($comprobante->estado_sunat !== ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['comprobante_id' => ['El comprobante original debe estar ACEPTADO por SUNAT.']]);
        }

        if (! $comprobante->venta || $comprobante->venta->estado === Venta::ANULADA) {
            throw ValidationException::withMessages(['venta' => ['La venta relacionada no es valida para nota de credito.']]);
        }

        if ($comprobante->venta->detalles->isEmpty()) {
            throw ValidationException::withMessages(['venta' => ['La venta relacionada no tiene detalles.']]);
        }

        return $comprobante;
    }

    protected function validarMotivo(string $codigo): MotivoNotaCredito
    {
        return MotivoNotaCredito::where('codigo', $codigo)->where('estado', true)->firstOrFail();
    }

    protected function detallesTotales(Venta $venta): array
    {
        return $venta->detalles->map(fn (VentaDetalle $detalle) => $this->detalleDesdeVentaDetalle($detalle, (float) $detalle->cantidad_presentacion))->all();
    }

    protected function detallesParciales(Venta $venta, array $items): array
    {
        return collect($items)->map(function (array $item) use ($venta) {
            $detalle = $venta->detalles->firstWhere('id', (int) $item['venta_detalle_id']);

            if (! $detalle) {
                throw ValidationException::withMessages(['detalles' => ['El detalle no pertenece a la venta original.']]);
            }

            $cantidad = round((float) $item['cantidad'], 4);

            if ($cantidad > (float) $detalle->cantidad_presentacion) {
                throw ValidationException::withMessages(['detalles.*.cantidad' => ['La cantidad de la nota supera la cantidad vendida.']]);
            }

            return $this->detalleDesdeVentaDetalle($detalle, $cantidad);
        })->all();
    }

    protected function detalleDesdeVentaDetalle(VentaDetalle $detalle, float $cantidad): array
    {
        $cantidadOriginal = max((float) $detalle->cantidad_presentacion, 0.0001);
        $factor = $cantidad / $cantidadOriginal;

        return [
            'tenant_id' => $detalle->tenant_id,
            'empresa_id' => $detalle->empresa_id,
            'venta_detalle_id' => $detalle->id,
            'producto_id' => $detalle->producto_id,
            'descripcion' => $detalle->descripcion,
            'unidad_medida' => $detalle->presentacion?->unidadMedida?->codigo_sunat ?: 'NIU',
            'cantidad' => $cantidad,
            'precio_unitario' => $detalle->precio_unitario,
            'descuento' => round((float) $detalle->descuento * $factor, 2),
            'subtotal' => round((float) $detalle->subtotal * $factor, 2),
            'igv' => round((float) $detalle->igv * $factor, 2),
            'total' => round((float) $detalle->total * $factor, 2),
        ];
    }

    protected function flagEfecto(array $data, string $field, string $motivoCodigo): bool
    {
        if (array_key_exists($field, $data)) {
            return (bool) $data[$field];
        }

        return in_array($motivoCodigo, ['01', '07', '08'], true);
    }

    protected function calcularTotales(array $detalles): array
    {
        return [
            'subtotal' => round(collect($detalles)->sum('subtotal'), 2),
            'descuento' => round(collect($detalles)->sum('descuento'), 2),
            'igv' => round(collect($detalles)->sum('igv'), 2),
            'total' => round(collect($detalles)->sum('total'), 2),
        ];
    }
}

