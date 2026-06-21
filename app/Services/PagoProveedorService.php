<?php

namespace App\Services;

use App\Models\CuentaPorPagar;
use App\Models\PagoProveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoProveedorService
{
    public function __construct(private readonly CajaService $cajaService, private readonly CuentaPorPagarService $cuentaPorPagarService)
    {
    }

    public function listar(Request $request)
    {
        return PagoProveedor::with(['proveedor', 'cuentaPorPagar.compra', 'caja', 'creadoPor'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('proveedor_id'), fn ($q) => $q->where('proveedor_id', $request->integer('proveedor_id')))
            ->when($request->filled('cuenta_por_pagar_id'), fn ($q) => $q->where('cuenta_por_pagar_id', $request->integer('cuenta_por_pagar_id')))
            ->when($request->filled('fecha_inicio'), fn ($q) => $q->whereDate('fecha_pago', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($q) => $q->whereDate('fecha_pago', '<=', $request->input('fecha_fin')))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));
    }

    public function obtener(int $id, array $scope): PagoProveedor
    {
        return PagoProveedor::with(['proveedor', 'cuentaPorPagar.compra', 'caja', 'creadoPor', 'anuladoPor'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function registrar(array $data): PagoProveedor
    {
        return DB::transaction(function () use ($data) {
            $cuenta = CuentaPorPagar::with('proveedor')
                ->where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($data['cuenta_por_pagar_id']);

            if (in_array($cuenta->estado, [CuentaPorPagar::ANULADO, CuentaPorPagar::ANULADA, CuentaPorPagar::PAGADO, CuentaPorPagar::PAGADA], true)) {
                throw ValidationException::withMessages(['cuenta_por_pagar_id' => ['La cuenta por pagar no admite nuevos pagos.']]);
            }

            $monto = round((float) $data['monto'], 2);
            if ($monto > round((float) $cuenta->saldo, 2)) {
                throw ValidationException::withMessages(['monto' => ['No se puede pagar mas que el saldo pendiente.']]);
            }

            $pago = PagoProveedor::create([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'cuenta_por_pagar_id' => $cuenta->id,
                'proveedor_id' => $cuenta->proveedor_id,
                'metodo_pago' => $data['metodo_pago'],
                'monto' => $monto,
                'referencia' => $data['referencia'] ?? null,
                'fecha_pago' => $data['fecha_pago'],
                'observacion' => $data['observacion'] ?? null,
                'estado' => PagoProveedor::REGISTRADO,
                'created_by' => $data['user_id'],
            ]);

            $movimiento = $this->cajaService->registrarEgreso([
                'tenant_id' => $cuenta->tenant_id,
                'empresa_id' => $cuenta->empresa_id,
                'tienda_id' => $cuenta->tienda_id,
                'user_id' => $data['user_id'],
                'metodo_pago' => $data['metodo_pago'],
                'concepto' => 'Pago a proveedor '.$cuenta->proveedor->razon_social,
                'monto' => $monto,
                'referencia_tipo' => 'PAGO_PROVEEDOR',
                'referencia_id' => $pago->id,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $pago->update(['caja_id' => $movimiento->caja_id]);

            $cuenta->monto_pagado = round((float) $cuenta->monto_pagado + $monto, 2);
            $cuenta->saldo = round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2);
            $this->cuentaPorPagarService->actualizarEstado($cuenta);

            return $this->obtener($pago->id, $data);
        });
    }

    public function anular(int $id, string $motivo, array $scope): PagoProveedor
    {
        return DB::transaction(function () use ($id, $motivo, $scope) {
            $pago = PagoProveedor::where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($pago->estado === PagoProveedor::ANULADO) {
                throw ValidationException::withMessages(['pago' => ['El pago ya esta anulado.']]);
            }

            $cuenta = CuentaPorPagar::where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($pago->cuenta_por_pagar_id);

            if (in_array($cuenta->estado, [CuentaPorPagar::ANULADO, CuentaPorPagar::ANULADA], true)) {
                throw ValidationException::withMessages(['cuenta' => ['No se puede anular pagos de una cuenta anulada.']]);
            }

            $this->cajaService->registrarIngreso([
                'tenant_id' => $pago->tenant_id,
                'empresa_id' => $pago->empresa_id,
                'tienda_id' => $pago->tienda_id,
                'user_id' => $scope['user_id'],
                'metodo_pago' => $pago->metodo_pago,
                'concepto' => 'Anulacion de pago a proveedor '.$pago->proveedor->razon_social,
                'monto' => $pago->monto,
                'referencia_tipo' => 'ANULACION_PAGO_PROVEEDOR',
                'referencia_id' => $pago->id,
                'observacion' => $motivo,
            ]);

            $pago->update([
                'estado' => PagoProveedor::ANULADO,
                'anulado_by' => $scope['user_id'],
                'anulado_at' => now(),
                'observacion' => trim(($pago->observacion ? $pago->observacion.' | ' : '').'Anulado: '.$motivo),
            ]);

            $cuenta->monto_pagado = round(max(0, (float) $cuenta->monto_pagado - (float) $pago->monto), 2);
            $cuenta->saldo = round((float) $cuenta->monto_total - (float) $cuenta->monto_pagado, 2);
            $this->cuentaPorPagarService->actualizarEstado($cuenta);

            return $this->obtener($pago->id, $scope);
        });
    }
}