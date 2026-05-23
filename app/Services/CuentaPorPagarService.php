<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CuentaPorPagar;
use App\Models\CuentaPorPagarPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorPagarService
{
    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function registrarPago(int $cuentaPorPagarId, array $data): CuentaPorPagar
    {
        return DB::transaction(function () use ($cuentaPorPagarId, $data) {
            $cuenta = CuentaPorPagar::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($cuentaPorPagarId);

            if ($cuenta->estado === CuentaPorPagar::ANULADA) {
                throw ValidationException::withMessages(['cuenta' => ['La cuenta por pagar está anulada.']]);
            }

            if ((float) $data['monto'] > (float) $cuenta->saldo) {
                throw ValidationException::withMessages(['monto' => ['No se puede pagar más que el saldo pendiente.']]);
            }

            $cajaId = $this->registrarEgresoCaja($cuenta, $data);

            CuentaPorPagarPago::create([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'cuenta_por_pagar_id' => $cuenta->id,
                'caja_id' => $cajaId,
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

            return $cuenta->refresh()->load(['proveedor', 'compra', 'pagos']);
        });
    }

    public function actualizarEstado(CuentaPorPagar $cuenta): void
    {
        if ((float) $cuenta->saldo <= 0) {
            $cuenta->estado = CuentaPorPagar::PAGADA;
        } elseif ((float) $cuenta->monto_pagado > 0) {
            $cuenta->estado = CuentaPorPagar::PARCIAL;
        } else {
            $cuenta->estado = CuentaPorPagar::PENDIENTE;
        }

        $cuenta->save();
    }

    protected function registrarEgresoCaja(CuentaPorPagar $cuenta, array $data): ?int
    {
        $movimiento = $this->cajaService->registrarEgreso([
            'tenant_id' => $cuenta->tenant_id,
            'empresa_id' => $cuenta->empresa_id,
            'tienda_id' => $cuenta->tienda_id,
            'user_id' => $data['user_id'],
            'metodo_pago' => $data['metodo_pago'],
            'concepto' => 'Pago a proveedor '.$cuenta->proveedor->razon_social,
            'monto' => $data['monto'],
            'referencia_tipo' => 'CUENTA_POR_PAGAR',
            'referencia_id' => $cuenta->id,
            'observacion' => $data['observacion'] ?? null,
        ]);

        return $movimiento->caja_id;
    }
}
