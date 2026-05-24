<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\ComprobanteElectronico;
use App\Models\CuentaPorCobrar;
use App\Models\MotivoNotaDebito;
use App\Models\NotaDebito;
use App\Models\SerieComprobante;
use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaDebitoService
{
    private const IGV = 0.18;

    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function listar(array $filters, array $scope): LengthAwarePaginator
    {
        return NotaDebito::with(['venta.cliente', 'comprobante', 'motivo'])
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

    public function obtener(int $id, array $scope): NotaDebito
    {
        return NotaDebito::with(['venta.cliente', 'venta.cuentaPorCobrar', 'comprobante.venta.cliente', 'motivo', 'detalles', 'cajaMovimiento'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function crear(array $data, array $scope): NotaDebito
    {
        return DB::transaction(function () use ($data, $scope) {
            $comprobante = $this->validarComprobante($data['comprobante_id'], $scope);
            $venta = $comprobante->venta;
            $motivo = $this->validarMotivo($data['motivo_codigo']);
            $detalles = $this->calcularDetalles($data['detalles']);
            $totales = $this->calcularTotales($detalles);

            if ($totales['total'] <= 0) {
                throw ValidationException::withMessages(['total' => ['El total de la nota de debito debe ser mayor a 0.']]);
            }

            $numero = $this->generarNumero($scope['tienda_id'], $comprobante->tipo_comprobante);

            $nota = NotaDebito::create([
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
                'subtotal' => $totales['subtotal'],
                'total_igv' => $totales['igv'],
                'total' => $totales['total'],
                'afecta_caja' => (bool) ($data['afecta_caja'] ?? false),
                'observacion' => $data['observacion'] ?? null,
                'estado' => NotaDebito::REGISTRADA,
                'created_by' => $scope['user_id'],
            ]);

            $nota->detalles()->createMany($detalles);
            $this->aplicarCuentaPorCobrar($nota);

            if ($nota->afecta_caja) {
                $this->aplicarCaja($nota, $data + $scope);
            }

            return $this->obtener($nota->id, $scope);
        });
    }

    public function anular(NotaDebito $nota, string $motivo, array $scope): NotaDebito
    {
        return DB::transaction(function () use ($nota, $motivo, $scope) {
            $nota = NotaDebito::where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($nota->id);

            if ($nota->estado === NotaDebito::ANULADA) {
                throw ValidationException::withMessages(['nota_debito' => ['La nota de debito ya esta anulada.']]);
            }

            if ($nota->caja_aplicada) {
                $this->cajaService->registrarEgreso([
                    'tenant_id' => $scope['tenant_id'],
                    'empresa_id' => $scope['empresa_id'],
                    'tienda_id' => $scope['tienda_id'],
                    'user_id' => $scope['user_id'],
                    'metodo_pago' => $nota->cajaMovimiento?->metodo_pago ?: 'EFECTIVO',
                    'concepto' => 'Anulacion nota de debito '.$nota->numero_completo,
                    'monto' => $nota->total,
                    'referencia_tipo' => 'ANULACION_NOTA_DEBITO',
                    'referencia_id' => $nota->id,
                    'observacion' => $motivo,
                ]);
            }

            $this->revertirCuentaPorCobrar($nota);

            $nota->update([
                'estado' => NotaDebito::ANULADA,
                'anulado_by' => $scope['user_id'],
                'anulado_at' => now(),
                'observacion' => trim(($nota->observacion ? $nota->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);

            return $this->obtener($nota->id, $scope);
        });
    }

    public function generarNumero(int $tiendaId, ?string $tipoComprobanteReferencia = null): array
    {
        $prefijo = match ($tipoComprobanteReferencia) {
            Venta::BOLETA => 'B',
            Venta::FACTURA => 'F',
            default => null,
        };

        $query = SerieComprobante::where('tienda_id', $tiendaId)
            ->where('tipo_comprobante', 'NOTA_DEBITO')
            ->where('estado', true);

        if ($prefijo) {
            $query->where('serie', 'ILIKE', $prefijo.'%');
        }

        $serie = $query->orderBy('serie')->lockForUpdate()->first();

        if (! $serie) {
            $serieSugerida = $tipoComprobanteReferencia === Venta::BOLETA ? 'BD01' : 'FD01';
            throw ValidationException::withMessages(['serie' => ["No existe serie activa {$serieSugerida} para NOTA_DEBITO en la tienda activa."]]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [
            'serie' => $serie->serie,
            'correlativo' => $correlativo,
            'numero_completo' => $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        ];
    }

    public function aplicarCaja(NotaDebito $nota, array $options = []): void
    {
        if ($nota->caja_aplicada || ! $nota->afecta_caja || $nota->estado === NotaDebito::ANULADA) {
            return;
        }

        try {
            $movimiento = $this->cajaService->registrarIngreso([
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
                'user_id' => $options['user_id'] ?? $nota->created_by,
                'metodo_pago' => $options['metodo_pago_cobro'] ?? 'EFECTIVO',
                'concepto' => 'Nota de debito '.$nota->numero_completo,
                'monto' => $nota->total,
                'referencia_tipo' => 'NOTA_DEBITO',
                'referencia_id' => $nota->id,
                'observacion' => $options['observacion_caja'] ?? null,
            ]);

            $nota->update([
                'caja_aplicada' => true,
                'caja_aplicada_at' => now(),
                'caja_movimiento_id' => $movimiento->id,
            ]);
        } catch (ValidationException) {
            $nota->update(['caja_aplicada' => false]);
        }
    }

    protected function validarComprobante(int $comprobanteId, array $scope): ComprobanteElectronico
    {
        $comprobante = ComprobanteElectronico::with(['venta.cliente', 'venta.cuentaPorCobrar'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($comprobanteId);

        if (! in_array($comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_id' => ['La nota de debito solo aplica para BOLETA o FACTURA.']]);
        }

        if ($comprobante->estado_sunat !== ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['comprobante_id' => ['El comprobante original debe estar ACEPTADO por SUNAT.']]);
        }

        if (! $comprobante->venta || $comprobante->venta->estado === Venta::ANULADA) {
            throw ValidationException::withMessages(['venta' => ['La venta relacionada no es valida para nota de debito.']]);
        }

        return $comprobante;
    }

    protected function validarMotivo(string $codigo): MotivoNotaDebito
    {
        return MotivoNotaDebito::where('codigo', $codigo)->where('estado', true)->firstOrFail();
    }

    protected function calcularDetalles(array $items): array
    {
        return collect($items)->map(function (array $item) {
            $cantidad = round((float) $item['cantidad'], 4);
            $precio = round((float) $item['precio_unitario'], 2);
            $total = round($cantidad * $precio, 2);
            $subtotal = round($total / (1 + self::IGV), 2);
            $igv = round($total - $subtotal, 2);

            return [
                'descripcion' => trim($item['descripcion']),
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
            ];
        })->all();
    }

    protected function calcularTotales(array $detalles): array
    {
        return [
            'subtotal' => round(collect($detalles)->sum('subtotal'), 2),
            'igv' => round(collect($detalles)->sum('igv'), 2),
            'total' => round(collect($detalles)->sum('total'), 2),
        ];
    }

    protected function aplicarCuentaPorCobrar(NotaDebito $nota): void
    {
        if ($nota->venta?->tipo_venta !== Venta::CREDITO) {
            return;
        }

        $cuenta = CuentaPorCobrar::where('venta_id', $nota->venta_id)->lockForUpdate()->first();
        if (! $cuenta || $cuenta->estado === CuentaPorCobrar::ANULADA) {
            return;
        }

        $cuenta->monto_total = round((float) $cuenta->monto_total + (float) $nota->total, 2);
        $cuenta->saldo = round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2);
        $cuenta->estado = $cuenta->saldo <= 0 ? CuentaPorCobrar::PAGADA : ((float) $cuenta->monto_pagado > 0 ? CuentaPorCobrar::PARCIAL : CuentaPorCobrar::PENDIENTE);
        $cuenta->save();
    }

    protected function revertirCuentaPorCobrar(NotaDebito $nota): void
    {
        if ($nota->venta?->tipo_venta !== Venta::CREDITO) {
            return;
        }

        $cuenta = CuentaPorCobrar::where('venta_id', $nota->venta_id)->lockForUpdate()->first();
        if (! $cuenta || $cuenta->estado === CuentaPorCobrar::ANULADA) {
            return;
        }

        $cuenta->monto_total = max(0, round((float) $cuenta->monto_total - (float) $nota->total, 2));
        $cuenta->saldo = max(0, round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2));
        $cuenta->estado = $cuenta->saldo <= 0 ? CuentaPorCobrar::PAGADA : ((float) $cuenta->monto_pagado > 0 ? CuentaPorCobrar::PARCIAL : CuentaPorCobrar::PENDIENTE);
        $cuenta->save();
    }
}