<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\ComprobanteBajaHistorial;
use App\Models\ComprobanteElectronico;
use App\Models\CuentaPorCobrar;
use App\Models\InventarioMovimiento;
use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComprobanteBajaService
{
    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly CajaService $cajaService,
        private readonly CuentaPorCobrarService $cuentaPorCobrarService,
        private readonly NotaCreditoEfectosService $notaCreditoEfectosService
    ) {
    }

    public function solicitarBaja(int $comprobanteId, string $motivo, array $scope): ComprobanteElectronico
    {
        return DB::transaction(function () use ($comprobanteId, $motivo, $scope) {
            $comprobante = $this->obtenerComprobante($comprobanteId, $scope);
            $this->validarPuedeBajarse($comprobante);

            match ($comprobante->tipo_comprobante) {
                Venta::BOLETA, Venta::FACTURA => $this->revertirBoletaFactura($comprobante, $motivo, $scope),
                'NOTA_CREDITO' => $this->revertirNotaCredito($comprobante, $motivo, $scope),
                'NOTA_DEBITO' => $this->revertirNotaDebito($comprobante, $motivo, $scope),
                default => throw ValidationException::withMessages(['tipo_comprobante' => ['Documento no permitido para baja interna.']]),
            };

            $estadoAnterior = $comprobante->estado_baja ?: ComprobanteElectronico::BAJA_SIN_BAJA;
            $comprobante->update([
                'estado_baja' => ComprobanteElectronico::BAJA_PENDIENTE,
                'motivo_baja' => $motivo,
                'fecha_solicitud_baja' => now(),
                'solicitado_baja_por' => $scope['user_id'],
            ]);

            ComprobanteBajaHistorial::create([
                'tenant_id' => $comprobante->tenant_id,
                'empresa_id' => $comprobante->empresa_id,
                'comprobante_id' => $comprobante->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => ComprobanteElectronico::BAJA_PENDIENTE,
                'motivo' => $motivo,
                'usuario_id' => $scope['user_id'],
            ]);

            return $this->cargarComprobante($comprobante->refresh());
        });
    }

    public function historial(int $comprobanteId, array $scope)
    {
        $comprobante = ComprobanteElectronico::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($comprobanteId);

        return ComprobanteBajaHistorial::with('usuario')
            ->where('comprobante_id', $comprobante->id)
            ->orderByDesc('created_at')
            ->get();
    }

    protected function revertirBoletaFactura(ComprobanteElectronico $comprobante, string $motivo, array $scope): void
    {
        $venta = $comprobante->venta;
        if (! $venta || $venta->estado === Venta::ANULADA) {
            throw ValidationException::withMessages(['venta' => ['La venta relacionada no es valida para baja interna.']]);
        }

        foreach ($venta->detalles as $detalle) {
            $this->inventarioService->aumentarStock([
                'tenant_id' => $venta->tenant_id,
                'empresa_id' => $venta->empresa_id,
                'tienda_id' => $venta->tienda_id,
                'producto_id' => $detalle->producto_id,
                'producto_presentacion_id' => $detalle->producto_presentacion_id,
                'lote_id' => $detalle->lote_id,
                'cantidad_presentacion' => $detalle->cantidad_presentacion,
                'motivo' => 'Baja interna '.$comprobante->numero_comprobante,
                'tipo_movimiento' => InventarioMovimiento::DEVOLUCION,
                'referencia_tipo' => 'BAJA_COMPROBANTE',
                'referencia_id' => $comprobante->id,
                'observacion' => $motivo,
                'user_id' => $scope['user_id'],
            ]);
        }

        $venta->loadMissing('pagos');
        foreach ($venta->pagos->where('estado', 'REGISTRADO') as $pago) {
            if ($pago->metodo_pago === 'CREDITO') {
                continue;
            }

            try {
                $this->cajaService->registrarEgreso([
                    'tenant_id' => $venta->tenant_id,
                    'empresa_id' => $venta->empresa_id,
                    'tienda_id' => $venta->tienda_id,
                    'user_id' => $scope['user_id'],
                    'metodo_pago' => $pago->metodo_pago,
                    'concepto' => 'Baja interna '.$comprobante->numero_comprobante,
                    'monto' => $pago->monto,
                    'referencia_tipo' => 'BAJA_COMPROBANTE',
                    'referencia_id' => $comprobante->id,
                    'observacion' => $motivo,
                ]);
            } catch (ValidationException $e) {
                if (! collect($e->errors())->has('caja')) {
                    throw $e;
                }
            }
        }

        if ($venta->tipo_venta === Venta::CREDITO) {
            $this->cuentaPorCobrarService->anularPorVenta($venta->id, 'Baja interna '.$comprobante->numero_comprobante.': '.$motivo);
        }
    }

    protected function revertirNotaCredito(ComprobanteElectronico $comprobante, string $motivo, array $scope): void
    {
        $nota = NotaCredito::with(['detalles.ventaDetalle', 'venta', 'cajaMovimiento'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('estado', '!=', NotaCredito::ANULADA)
            ->where(function ($query) use ($comprobante) {
                $query->where('id', $comprobante->documento_origen_id)
                    ->orWhere('id', $comprobante->nota_electronica_id);
            })
            ->first();

        if (! $nota) {
            $nota = NotaCredito::with(['detalles.ventaDetalle', 'venta', 'cajaMovimiento'])
                ->where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->where('numero_completo', $comprobante->numero_comprobante)
                ->where('estado', '!=', NotaCredito::ANULADA)
                ->firstOrFail();
        }

        $nota->motivo_anulacion = 'Baja interna '.$comprobante->numero_comprobante.': '.$motivo;
        $nota->anulado_by = $scope['user_id'];
        $this->notaCreditoEfectosService->revertirEfectosSiAnula($nota);
    }

    protected function revertirNotaDebito(ComprobanteElectronico $comprobante, string $motivo, array $scope): void
    {
        $nota = NotaDebito::with(['venta', 'cajaMovimiento'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where(function ($query) use ($comprobante) {
                $query->where('id', $comprobante->documento_origen_id)
                    ->orWhere('id', $comprobante->nota_electronica_id);
            })
            ->first();

        if (! $nota) {
            $nota = NotaDebito::with(['venta', 'cajaMovimiento'])
                ->where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->where('numero_completo', $comprobante->numero_comprobante)
                ->firstOrFail();
        }

        if ($nota->caja_aplicada) {
            try {
                $this->cajaService->registrarEgreso([
                    'tenant_id' => $nota->tenant_id,
                    'empresa_id' => $nota->empresa_id,
                    'tienda_id' => $nota->tienda_id,
                    'user_id' => $scope['user_id'],
                    'metodo_pago' => $nota->cajaMovimiento?->metodo_pago ?: 'EFECTIVO',
                    'concepto' => 'Baja interna nota de debito '.$nota->numero_completo,
                    'monto' => $nota->total,
                    'referencia_tipo' => 'BAJA_COMPROBANTE',
                    'referencia_id' => $comprobante->id,
                    'observacion' => $motivo,
                ]);
            } catch (ValidationException $e) {
                if (! collect($e->errors())->has('caja')) {
                    throw $e;
                }
            }
            $nota->update(['caja_aplicada' => false]);
        }
    }

    protected function validarPuedeBajarse(ComprobanteElectronico $comprobante): void
    {
        if (! in_array($comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA, 'NOTA_CREDITO', 'NOTA_DEBITO'], true)) {
            throw ValidationException::withMessages(['tipo_comprobante' => ['Documento no permitido para baja interna.']]);
        }

        if ($comprobante->estado_sunat !== ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['estado_sunat' => ['Solo se puede solicitar baja interna de comprobantes ACEPTADOS por SUNAT.']]);
        }

        if (($comprobante->estado_baja ?: ComprobanteElectronico::BAJA_SIN_BAJA) !== ComprobanteElectronico::BAJA_SIN_BAJA) {
            throw ValidationException::withMessages(['estado_baja' => ['El comprobante ya tiene una baja solicitada o procesada.']]);
        }

        if ($comprobante->venta?->estado === Venta::ANULADA) {
            throw ValidationException::withMessages(['venta' => ['La venta ya esta anulada internamente.']]);
        }
    }

    protected function obtenerComprobante(int $comprobanteId, array $scope): ComprobanteElectronico
    {
        return ComprobanteElectronico::with(['venta.detalles', 'venta.pagos', 'venta.cuentaPorCobrar'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->lockForUpdate()
            ->findOrFail($comprobanteId);
    }

    protected function cargarComprobante(ComprobanteElectronico $comprobante): ComprobanteElectronico
    {
        return $comprobante->load(['venta.cliente', 'venta.detalles.producto', 'venta.detalles.presentacion.unidadMedida', 'bajaHistorial.usuario', 'solicitadoBajaPor']);
    }
}
