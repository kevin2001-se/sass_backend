<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\CuentaPorPagar;
use App\Models\CuentaPorPagarPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaPorPagarService
{
    public function __construct(private readonly CajaService $cajaService)
    {
    }

    public function listar(Request $request)
    {
        return CuentaPorPagar::with(['proveedor', 'compra', 'tienda'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->when($request->filled('fecha_inicio'), fn ($q) => $q->whereDate('fecha_emision', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($q) => $q->whereDate('fecha_emision', '<=', $request->input('fecha_fin')))
            ->when($request->boolean('vencidas'), fn ($q) => $q->whereNotIn('estado', [CuentaPorPagar::PAGADO, CuentaPorPagar::PAGADA, CuentaPorPagar::ANULADO, CuentaPorPagar::ANULADA])->whereDate('fecha_vencimiento', '<', now()->toDateString())->where('saldo', '>', 0))
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estado = $request->input('estado');
                if ($estado === CuentaPorPagar::VENCIDO) {
                    $q->whereNotIn('estado', [CuentaPorPagar::PAGADO, CuentaPorPagar::PAGADA, CuentaPorPagar::ANULADO, CuentaPorPagar::ANULADA])
                        ->whereDate('fecha_vencimiento', '<', now()->toDateString())
                        ->where('saldo', '>', 0);
                    return;
                }
                $q->where('estado', $estado);
            })
            ->orderByRaw('fecha_vencimiento is null')
            ->orderBy('fecha_vencimiento')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));
    }

    public function obtener(int $id, array $scope): CuentaPorPagar
    {
        return CuentaPorPagar::with(['proveedor', 'compra.detalles.producto', 'compra.detalles.presentacion', 'tienda', 'pagos'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function crearDesdeCompra(Compra $compra): CuentaPorPagar
    {
        return CuentaPorPagar::updateOrCreate(
            [
                'empresa_id' => $compra->empresa_id,
                'compra_id' => $compra->id,
            ],
            [
                'tenant_id' => $compra->tenant_id,
                'tienda_id' => $compra->tienda_id,
                'proveedor_id' => $compra->proveedor_id,
                'fecha_emision' => $compra->fecha_emision,
                'fecha_vencimiento' => $compra->fecha_vencimiento,
                'monto_total' => $compra->total,
                'monto_pagado' => 0,
                'saldo' => $compra->total,
                'estado' => CuentaPorPagar::PENDIENTE,
                'observacion' => $compra->observacion,
            ]
        );
    }

    public function anularPorCompraSiSinPagos(Compra $compra): void
    {
        $cuenta = CuentaPorPagar::where('tenant_id', $compra->tenant_id)
            ->where('empresa_id', $compra->empresa_id)
            ->where('tienda_id', $compra->tienda_id)
            ->where('compra_id', $compra->id)
            ->lockForUpdate()
            ->first();

        if (! $cuenta) {
            return;
        }

        if ((float) $cuenta->monto_pagado > 0 || $cuenta->pagos()->exists()) {
            throw ValidationException::withMessages([
                'cuenta_por_pagar' => ['No se puede anular la compra porque la cuenta por pagar ya tiene pagos registrados.'],
            ]);
        }

        $cuenta->update([
            'estado' => CuentaPorPagar::ANULADO,
            'saldo' => 0,
            'observacion' => trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').'Anulada por anulación de compra '.$compra->serie.'-'.$compra->numero),
        ]);
    }

    public function registrarPago(int $cuentaPorPagarId, array $data): CuentaPorPagar
    {
        return DB::transaction(function () use ($cuentaPorPagarId, $data) {
            $cuenta = CuentaPorPagar::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($cuentaPorPagarId);

            if (in_array($cuenta->estado, [CuentaPorPagar::ANULADO, CuentaPorPagar::ANULADA], true)) {
                throw ValidationException::withMessages(['cuenta' => ['La cuenta por pagar esta anulada.']]);
            }

            if ((float) $data['monto'] > (float) $cuenta->saldo) {
                throw ValidationException::withMessages(['monto' => ['No se puede pagar mas que el saldo pendiente.']]);
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

            return $cuenta->refresh()->load(['proveedor', 'compra', 'tienda', 'pagos']);
        });
    }

    public function actualizarEstado(CuentaPorPagar $cuenta): void
    {
        if ((float) $cuenta->saldo <= 0) {
            $cuenta->estado = CuentaPorPagar::PAGADO;
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