<?php

namespace App\Services;

use App\Models\CuentaPorCobrar;
use App\Models\CuentaPorCobrarPago;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorCobrarService
{
    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function crearDesdeVenta(Venta $venta): ?CuentaPorCobrar
    {
        if ($venta->tipo_venta !== Venta::CREDITO) {
            return null;
        }

        return DB::transaction(function () use ($venta) {
            if (! $venta->cliente_id) {
                throw ValidationException::withMessages([
                    'cliente_id' => ['Una venta crÃƒÂ©dito debe tener cliente.'],
                ]);
            }

            $venta->loadMissing(['cliente', 'pagos']);

            $cuenta = CuentaPorCobrar::create([
                'tenant_id' => $venta->tenant_id,
                'empresa_id' => $venta->empresa_id,
                'tienda_id' => $venta->tienda_id,
                'cliente_id' => $venta->cliente_id,
                'venta_id' => $venta->id,
                'monto_total' => $venta->total,
                'monto_pagado' => 0,
                'saldo' => $venta->total,
                'fecha_emision' => $venta->fecha_emision?->toDateString() ?? now()->toDateString(),
                'fecha_vencimiento' => null,
                'estado' => CuentaPorCobrar::PENDIENTE,
                'observacion' => $venta->observacion,
            ]);

            foreach ($venta->pagos->where('estado', 'REGISTRADO') as $pago) {
                if ($pago->metodo_pago === 'CREDITO') {
                    continue;
                }

                $this->registrarPago($cuenta->id, [
                    'tenant_id' => $venta->tenant_id,
                    'empresa_id' => $venta->empresa_id,
                    'tienda_id' => $venta->tienda_id,
                    'user_id' => $venta->user_id,
                    'metodo_pago' => $pago->metodo_pago,
                    'monto' => $pago->monto,
                    'fecha_pago' => $venta->fecha_emision?->toDateString() ?? now()->toDateString(),
                    'referencia' => $pago->referencia,
                    'observacion' => 'Pago inicial de venta '.$venta->numero_comprobante,
                ]);
            }

            return $cuenta->refresh()->load(['cliente', 'venta', 'pagos']);
        });
    }

    public function registrarPago(int $cuentaPorCobrarId, array $data): CuentaPorCobrar
    {
        return DB::transaction(function () use ($cuentaPorCobrarId, $data) {
            $cuenta = CuentaPorCobrar::with('cliente')
                ->where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($cuentaPorCobrarId);

            if (in_array($cuenta->estado, [CuentaPorCobrar::PAGADA, CuentaPorCobrar::ANULADA], true)) {
                throw ValidationException::withMessages([
                    'cuenta' => ['No se pueden registrar pagos en una cuenta pagada o anulada.'],
                ]);
            }

            if ((float) $data['monto'] > (float) $cuenta->saldo) {
                throw ValidationException::withMessages([
                    'monto' => ['No se puede pagar mÃƒÂ¡s que el saldo pendiente.'],
                ]);
            }

            $movimiento = $this->cajaService->registrarIngreso([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'user_id' => $data['user_id'],
                'metodo_pago' => $data['metodo_pago'],
                'concepto' => 'Pago de cliente '.$cuenta->cliente->nombres,
                'monto' => $data['monto'],
                'referencia_tipo' => 'CUENTA_POR_COBRAR',
                'referencia_id' => $cuenta->id,
                'observacion' => $data['observacion'] ?? null,
            ]);

            CuentaPorCobrarPago::create([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'cuenta_por_cobrar_id' => $cuenta->id,
                'caja_id' => $movimiento->caja_id,
                'user_id' => $data['user_id'],
                'metodo_pago' => $data['metodo_pago'],
                'monto' => $data['monto'],
                'fecha_pago' => $data['fecha_pago'],
                'referencia' => $data['referencia'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'estado' => 'REGISTRADO',
            ]);

            $cuenta->monto_pagado = round((float) $cuenta->monto_pagado + (float) $data['monto'], 2);
            $cuenta->saldo = round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2);
            $this->actualizarEstado($cuenta);
            $cuenta->venta()->update([
                'monto_pagado' => $cuenta->monto_pagado,
                'saldo_pendiente' => $cuenta->saldo,
            ]);

            return $cuenta->refresh()->load(['cliente', 'venta', 'pagos']);
        });
    }


    public function anularPago(int $pagoId, array $data): CuentaPorCobrarPago
    {
        return DB::transaction(function () use ($pagoId, $data) {
            $pago = CuentaPorCobrarPago::with(['cuentaPorCobrar.venta'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($pagoId);

            if ($pago->estado !== 'REGISTRADO') {
                throw ValidationException::withMessages([
                    'pago' => ['Solo se pueden anular pagos registrados.'],
                ]);
            }

            $cuenta = CuentaPorCobrar::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($pago->cuenta_por_cobrar_id);

            $this->cajaService->registrarEgreso([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'user_id' => $data['user_id'],
                'metodo_pago' => $pago->metodo_pago,
                'concepto' => 'Anulacion de pago de cliente venta '.$cuenta->venta?->numero_comprobante,
                'monto' => $pago->monto,
                'referencia_tipo' => 'CUENTA_POR_COBRAR',
                'referencia_id' => $cuenta->id,
                'observacion' => $data['motivo'] ?? null,
            ]);

            $pago->update([
                'estado' => 'ANULADO',
                'anulado_by' => $data['user_id'],
                'anulado_at' => now(),
                'observacion' => trim(($pago->observacion ? $pago->observacion.' | ' : '').'ANULADO: '.($data['motivo'] ?? '')),
            ]);

            $cuenta->monto_pagado = round(max(0, (float) $cuenta->monto_pagado - (float) $pago->monto), 2);
            $cuenta->saldo = round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2);
            $this->actualizarEstado($cuenta);
            $cuenta->venta()->update([
                'monto_pagado' => $cuenta->monto_pagado,
                'saldo_pendiente' => $cuenta->saldo,
            ]);

            return $pago->refresh();
        });
    }
    public function actualizarEstado(CuentaPorCobrar $cuenta): void
    {
        if ((float) $cuenta->saldo <= 0) {
            $cuenta->estado = CuentaPorCobrar::PAGADA;
        } elseif ($cuenta->fecha_vencimiento && $cuenta->fecha_vencimiento->lt(today())) {
            $cuenta->estado = CuentaPorCobrar::VENCIDA;
        } elseif ((float) $cuenta->monto_pagado > 0) {
            $cuenta->estado = CuentaPorCobrar::PARCIAL;
        } else {
            $cuenta->estado = CuentaPorCobrar::PENDIENTE;
        }

        $cuenta->save();
    }

    public function marcarVencidas(): int
    {
        return CuentaPorCobrar::whereNotIn('estado', [CuentaPorCobrar::PAGADA, CuentaPorCobrar::ANULADA])
            ->where('saldo', '>', 0)
            ->whereDate('fecha_vencimiento', '<', today())
            ->update(['estado' => CuentaPorCobrar::VENCIDA]);
    }

    public function anularPorVenta(int $ventaId, string $motivo): void
    {
        DB::transaction(function () use ($ventaId, $motivo) {
            $cuenta = CuentaPorCobrar::with(['pagos', 'venta'])
                ->where('venta_id', $ventaId)
                ->lockForUpdate()
                ->first();

            if (! $cuenta || $cuenta->estado === CuentaPorCobrar::ANULADA) {
                return;
            }

            foreach ($cuenta->pagos->where('estado', 'REGISTRADO') as $pago) {
                $this->cajaService->registrarEgreso([
                    'tenant_id' => $cuenta->tenant_id,
                    'empresa_id' => $cuenta->empresa_id,
                    'tienda_id' => $cuenta->tienda_id,
                    'user_id' => $pago->user_id,
                    'metodo_pago' => $pago->metodo_pago,
                    'concepto' => 'AnulaciÃƒÂ³n de cobro venta '.$cuenta->venta->numero_comprobante,
                    'monto' => $pago->monto,
                    'referencia_tipo' => 'CUENTA_POR_COBRAR',
                    'referencia_id' => $cuenta->id,
                    'observacion' => $motivo,
                ]);
            }

            $cuenta->pagos()->update(['estado' => 'ANULADO']);
            $cuenta->update([
                'estado' => CuentaPorCobrar::ANULADA,
                'saldo' => 0,
                'observacion' => trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);
        });
    }
}
