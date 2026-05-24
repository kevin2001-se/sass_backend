<?php

namespace App\Services;

use App\Models\CuentaPorCobrar;
use App\Models\InventarioMovimiento;
use App\Models\NotaCredito;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaCreditoEfectosService
{
    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly CajaService $cajaService
    ) {
    }

    public function aplicarEfectos(NotaCredito $nota, array $options = []): NotaCredito
    {
        return DB::transaction(function () use ($nota, $options) {
            $nota = $this->cargarNotaBloqueada($nota->id);
            $this->validarNotaAplicable($nota);
            $this->validarCantidadDisponible($nota);

            if ($nota->afecta_stock && ! $nota->stock_aplicado) {
                $this->aplicarStock($nota);
            }

            if ($nota->afecta_caja && ! $nota->caja_aplicada) {
                $this->aplicarCaja($nota, $options);
            }

            return $nota->refresh()->load(['detalles.ventaDetalle', 'venta.cliente', 'comprobante', 'motivo', 'cajaMovimiento']);
        });
    }

    public function aplicarStock(NotaCredito $nota): void
    {
        if (! $nota->afecta_stock || $nota->stock_aplicado) {
            return;
        }

        foreach ($nota->detalles as $detalle) {
            $ventaDetalle = $detalle->ventaDetalle;

            $this->inventarioService->aumentarStock([
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
                'producto_id' => $detalle->producto_id,
                'producto_presentacion_id' => $ventaDetalle->producto_presentacion_id,
                'lote_id' => $ventaDetalle->lote_id,
                'cantidad_presentacion' => $detalle->cantidad,
                'motivo' => 'Nota de credito '.$nota->numero_completo,
                'tipo_movimiento' => InventarioMovimiento::DEVOLUCION,
                'referencia_tipo' => 'NOTA_CREDITO',
                'referencia_id' => $nota->id,
                'observacion' => $nota->observacion,
                'user_id' => $nota->created_by,
            ]);
        }

        $nota->update([
            'stock_aplicado' => true,
            'stock_aplicado_at' => now(),
        ]);
    }

    public function aplicarCaja(NotaCredito $nota, array $options = []): void
    {
        if (! $nota->afecta_caja || $nota->caja_aplicada) {
            return;
        }

        if ($nota->venta->tipo_venta === Venta::CREDITO) {
            $this->aplicarCuentaPorCobrar($nota);
            $nota->update([
                'caja_aplicada' => true,
                'caja_aplicada_at' => now(),
            ]);

            return;
        }

        try {
            $movimiento = $this->cajaService->registrarEgreso([
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
                'user_id' => $nota->created_by,
                'metodo_pago' => $options['metodo_pago_devolucion'] ?? 'EFECTIVO',
                'concepto' => 'Nota de credito '.$nota->numero_completo,
                'monto' => $nota->total,
                'referencia_tipo' => 'NOTA_CREDITO',
                'referencia_id' => $nota->id,
                'observacion' => $options['observacion_caja'] ?? $nota->observacion,
            ]);

            $nota->update([
                'caja_aplicada' => true,
                'caja_aplicada_at' => now(),
                'caja_movimiento_id' => $movimiento->id,
            ]);
        } catch (ValidationException $e) {
            if (! collect($e->errors())->has('caja')) {
                throw $e;
            }
        }
    }

    public function validarCantidadDisponible(NotaCredito $nota): void
    {
        $detallesAgrupados = $nota->detalles->groupBy('venta_detalle_id');

        foreach ($detallesAgrupados as $ventaDetalleId => $detalles) {
            $ventaDetalle = $detalles->first()->ventaDetalle;
            $cantidadNota = round((float) $detalles->sum(fn ($detalle) => (float) $detalle->cantidad), 4);
            $devueltoAnterior = $this->obtenerCantidadDevueltaAnterior((int) $ventaDetalleId, $nota->id);
            $disponible = round((float) $ventaDetalle->cantidad_presentacion - $devueltoAnterior, 4);

            if ($cantidadNota > $disponible) {
                throw ValidationException::withMessages([
                    'detalles' => ["La cantidad de {$ventaDetalle->descripcion} supera lo disponible para devolucion. Disponible: {$disponible}."],
                ]);
            }
        }
    }

    public function obtenerCantidadDevueltaAnterior(int $ventaDetalleId, ?int $notaCreditoId = null): float
    {
        return (float) DB::table('nota_credito_detalles as ncd')
            ->join('notas_credito as nc', 'nc.id', '=', 'ncd.nota_credito_id')
            ->where('ncd.venta_detalle_id', $ventaDetalleId)
            ->where('nc.estado', '!=', NotaCredito::ANULADA)
            ->when($notaCreditoId, fn ($query) => $query->where('nc.id', '!=', $notaCreditoId))
            ->sum('ncd.cantidad');
    }

    public function revertirEfectosSiAnula(NotaCredito $nota): void
    {
        $nota = $this->cargarNotaBloqueada($nota->id);

        if ($nota->stock_aplicado) {
            foreach ($nota->detalles as $detalle) {
                $ventaDetalle = $detalle->ventaDetalle;
                $this->inventarioService->disminuirStock([
                    'tenant_id' => $nota->tenant_id,
                    'empresa_id' => $nota->empresa_id,
                    'tienda_id' => $nota->tienda_id,
                    'producto_id' => $detalle->producto_id,
                    'producto_presentacion_id' => $ventaDetalle->producto_presentacion_id,
                    'lote_id' => $ventaDetalle->lote_id,
                    'cantidad_presentacion' => $detalle->cantidad,
                    'motivo' => 'Anulacion nota de credito '.$nota->numero_completo,
                    'tipo_movimiento' => InventarioMovimiento::SALIDA,
                    'referencia_tipo' => 'ANULACION_NOTA_CREDITO',
                    'referencia_id' => $nota->id,
                    'observacion' => $nota->motivo_anulacion,
                    'user_id' => $nota->anulado_by ?: $nota->created_by,
                ]);
            }

            $nota->update(['stock_aplicado' => false]);
        }

        if ($nota->caja_aplicada && $nota->venta?->tipo_venta === Venta::CREDITO) {
            $this->revertirCuentaPorCobrar($nota);
            $nota->update(['caja_aplicada' => false]);
        }

        if ($nota->caja_aplicada && $nota->caja_movimiento_id) {
            $this->cajaService->registrarIngreso([
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
                'user_id' => $nota->anulado_by ?: $nota->created_by,
                'metodo_pago' => $nota->cajaMovimiento?->metodo_pago ?: 'EFECTIVO',
                'concepto' => 'Anulacion nota de credito '.$nota->numero_completo,
                'monto' => $nota->cajaMovimiento?->monto ?: $nota->total,
                'referencia_tipo' => 'ANULACION_NOTA_CREDITO',
                'referencia_id' => $nota->id,
                'observacion' => $nota->motivo_anulacion,
            ]);

            $nota->update(['caja_aplicada' => false]);
        }
    }

    protected function aplicarCuentaPorCobrar(NotaCredito $nota): void
    {
        $cuenta = CuentaPorCobrar::where('venta_id', $nota->venta_id)->lockForUpdate()->first();

        if (! $cuenta || $cuenta->estado === CuentaPorCobrar::ANULADA) {
            return;
        }

        $totalNota = round((float) $nota->total, 2);
        $saldoAnterior = round((float) $cuenta->saldo, 2);
        $reduccionSaldo = min($totalNota, $saldoAnterior);

        $cuenta->monto_total = max(0, round((float) $cuenta->monto_total - $totalNota, 2));
        $cuenta->saldo = max(0, round($saldoAnterior - $reduccionSaldo, 2));
        $this->actualizarEstadoCuenta($cuenta);
        $cuenta->observacion = trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').'NC '.$nota->numero_completo);
        $cuenta->save();
    }

    protected function revertirCuentaPorCobrar(NotaCredito $nota): void
    {
        $cuenta = CuentaPorCobrar::where('venta_id', $nota->venta_id)->lockForUpdate()->first();

        if (! $cuenta || $cuenta->estado === CuentaPorCobrar::ANULADA) {
            return;
        }

        $cuenta->monto_total = round((float) $cuenta->monto_total + (float) $nota->total, 2);
        $cuenta->saldo = round((float) $cuenta->saldo + (float) $nota->total, 2);
        $this->actualizarEstadoCuenta($cuenta);
        $cuenta->observacion = trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').'ANULADA NC '.$nota->numero_completo);
        $cuenta->save();
    }

    protected function actualizarEstadoCuenta(CuentaPorCobrar $cuenta): void
    {
        if ((float) $cuenta->saldo <= 0) {
            $cuenta->estado = CuentaPorCobrar::PAGADA;
        } elseif ((float) $cuenta->monto_pagado > 0) {
            $cuenta->estado = CuentaPorCobrar::PARCIAL;
        } else {
            $cuenta->estado = CuentaPorCobrar::PENDIENTE;
        }
    }

    protected function validarNotaAplicable(NotaCredito $nota): void
    {
        if ($nota->estado === NotaCredito::ANULADA) {
            throw ValidationException::withMessages(['nota_credito' => ['No se pueden aplicar efectos a una nota anulada.']]);
        }

        if ($nota->venta?->estado === Venta::ANULADA) {
            throw ValidationException::withMessages(['venta' => ['No se pueden aplicar efectos porque la venta original esta anulada.']]);
        }
    }

    protected function cargarNotaBloqueada(int $notaId): NotaCredito
    {
        return NotaCredito::with(['detalles.ventaDetalle', 'venta.cuentaPorCobrar', 'cajaMovimiento'])
            ->lockForUpdate()
            ->findOrFail($notaId);
    }
}
