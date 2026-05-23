<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaService
{
    public function aperturarCaja(array $data): Caja
    {
        return DB::transaction(function () use ($data) {
            $existeCajaAbierta = Caja::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->where('estado', Caja::ABIERTA)
                ->lockForUpdate()
                ->exists();

            if ($existeCajaAbierta) {
                throw ValidationException::withMessages([
                    'caja' => ['Ya existe una caja abierta para esta tienda.'],
                ]);
            }

            $caja = Caja::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'user_apertura_id' => $data['user_id'],
                'fecha_apertura' => now(),
                'monto_apertura' => $data['monto_apertura'],
                'monto_cierre_sistema' => $data['monto_apertura'],
                'estado' => Caja::ABIERTA,
                'observacion_apertura' => $data['observacion_apertura'] ?? null,
            ]);

            $this->crearMovimiento($caja, [
                'user_id' => $data['user_id'],
                'tipo_movimiento' => CajaMovimiento::APERTURA,
                'metodo_pago' => 'EFECTIVO',
                'concepto' => 'Apertura de caja',
                'monto' => $data['monto_apertura'],
                'observacion' => $data['observacion_apertura'] ?? null,
            ]);

            return $this->cargarCaja($caja);
        });
    }

    public function cerrarCaja(int $cajaId, array $data): array
    {
        return DB::transaction(function () use ($cajaId, $data) {
            $caja = $this->obtenerCajaAbiertaPorId($cajaId, $data);
            $arqueo = $this->generarArqueo($caja->id, (float) $data['monto_cierre_real']);
            $diferencia = round($data['monto_cierre_real'] - $arqueo['saldo_sistema'], 2);

            $caja->update([
                'user_cierre_id' => $data['user_id'],
                'fecha_cierre' => now(),
                'monto_cierre_sistema' => $arqueo['saldo_sistema'],
                'monto_cierre_real' => $data['monto_cierre_real'],
                'diferencia' => $diferencia,
                'estado' => Caja::CERRADA,
                'observacion_cierre' => $data['observacion_cierre'] ?? null,
            ]);

            $arqueo['monto_real'] = round((float) $data['monto_cierre_real'], 2);
            $arqueo['diferencia'] = $diferencia;

            return [
                'caja' => $this->cargarCaja($caja->refresh()),
                'arqueo' => $arqueo,
            ];
        });
    }

    public function registrarIngreso(array $data): CajaMovimiento
    {
        return DB::transaction(function () use ($data) {
            $caja = $this->obtenerCajaAbierta($data);

            return $this->crearMovimiento($caja, [
                'user_id' => $data['user_id'],
                'tipo_movimiento' => CajaMovimiento::INGRESO,
                'metodo_pago' => $data['metodo_pago'],
                'concepto' => $data['concepto'],
                'monto' => $data['monto'],
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id' => $data['referencia_id'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ])->load(['caja', 'user']);
        });
    }

    public function registrarEgreso(array $data): CajaMovimiento
    {
        return DB::transaction(function () use ($data) {
            $caja = $this->obtenerCajaAbierta($data);

            return $this->crearMovimiento($caja, [
                'user_id' => $data['user_id'],
                'tipo_movimiento' => CajaMovimiento::EGRESO,
                'metodo_pago' => $data['metodo_pago'],
                'concepto' => $data['concepto'],
                'monto' => $data['monto'],
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id' => $data['referencia_id'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ])->load(['caja', 'user']);
        });
    }

    public function registrarMovimientoVenta(Venta $venta): void
    {
        if ($venta->tipo_venta !== Venta::CONTADO || $venta->estado !== Venta::REGISTRADA) {
            return;
        }

        DB::transaction(function () use ($venta) {
            $caja = $this->obtenerCajaAbierta([
                'tenant_id' => $venta->tenant_id,
                'empresa_id' => $venta->empresa_id,
                'tienda_id' => $venta->tienda_id,
            ]);

            foreach ($venta->pagos as $pago) {
                if ($pago->metodo_pago === 'CREDITO') {
                    continue;
                }

                $this->crearMovimiento($caja, [
                    'user_id' => $venta->user_id,
                    'tipo_movimiento' => CajaMovimiento::VENTA,
                    'metodo_pago' => $pago->metodo_pago,
                    'concepto' => 'Venta '.$venta->numero_comprobante,
                    'monto' => $pago->monto,
                    'referencia_tipo' => 'VENTA',
                    'referencia_id' => $venta->id,
                    'observacion' => $pago->referencia,
                ]);
            }
        });
    }

    public function registrarAnulacionVenta(Venta $venta, int $userId, ?string $motivo = null): void
    {
        if ($venta->tipo_venta !== Venta::CONTADO) {
            return;
        }

        DB::transaction(function () use ($venta, $userId, $motivo) {
            $caja = $this->obtenerCajaAbierta([
                'tenant_id' => $venta->tenant_id,
                'empresa_id' => $venta->empresa_id,
                'tienda_id' => $venta->tienda_id,
            ]);

            foreach ($venta->pagos as $pago) {
                if ($pago->metodo_pago === 'CREDITO') {
                    continue;
                }

                $this->crearMovimiento($caja, [
                    'user_id' => $userId,
                    'tipo_movimiento' => CajaMovimiento::ANULACION_VENTA,
                    'metodo_pago' => $pago->metodo_pago,
                    'concepto' => 'Anulación de venta '.$venta->numero_comprobante,
                    'monto' => $pago->monto,
                    'referencia_tipo' => 'VENTA',
                    'referencia_id' => $venta->id,
                    'observacion' => $motivo,
                ]);
            }
        });
    }

    public function obtenerResumenCaja(int $cajaId): array
    {
        return $this->generarArqueo($cajaId);
    }

    public function generarArqueo(int $cajaId, ?float $montoReal = null): array
    {
        $caja = Caja::with('movimientos')->findOrFail($cajaId);
        $movimientos = $caja->movimientos;

        $ingresosPorMetodo = fn (string $metodo) => round($movimientos
            ->whereIn('tipo_movimiento', [CajaMovimiento::INGRESO, CajaMovimiento::VENTA, CajaMovimiento::AJUSTE])
            ->where('metodo_pago', $metodo)
            ->sum(fn ($movimiento) => (float) $movimiento->monto), 2);

        $totalIngresos = round($movimientos
            ->whereIn('tipo_movimiento', [CajaMovimiento::INGRESO, CajaMovimiento::VENTA, CajaMovimiento::AJUSTE])
            ->sum(fn ($movimiento) => (float) $movimiento->monto), 2);

        $totalEgresos = round($movimientos
            ->whereIn('tipo_movimiento', [CajaMovimiento::EGRESO, CajaMovimiento::ANULACION_VENTA])
            ->sum(fn ($movimiento) => (float) $movimiento->monto), 2);

        $saldoSistema = round((float) $caja->monto_apertura + $totalIngresos - $totalEgresos, 2);
        $montoReal ??= $caja->monto_cierre_real !== null ? (float) $caja->monto_cierre_real : null;

        return [
            'monto_apertura' => round((float) $caja->monto_apertura, 2),
            'ingresos_efectivo' => $ingresosPorMetodo('EFECTIVO'),
            'ingresos_yape' => $ingresosPorMetodo('YAPE'),
            'ingresos_plin' => $ingresosPorMetodo('PLIN'),
            'ingresos_tarjeta' => $ingresosPorMetodo('TARJETA'),
            'ingresos_transferencia' => $ingresosPorMetodo('TRANSFERENCIA'),
            'total_ingresos' => $totalIngresos,
            'total_egresos' => $totalEgresos,
            'saldo_sistema' => $saldoSistema,
            'monto_real' => $montoReal !== null ? round($montoReal, 2) : null,
            'diferencia' => $montoReal !== null ? round($montoReal - $saldoSistema, 2) : null,
        ];
    }

    protected function obtenerCajaAbierta(array $scope): Caja
    {
        $caja = Caja::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('estado', Caja::ABIERTA)
            ->lockForUpdate()
            ->first();

        if (! $caja) {
            throw ValidationException::withMessages([
                'caja' => ['No hay una caja abierta para esta tienda.'],
            ]);
        }

        return $caja;
    }

    protected function obtenerCajaAbiertaPorId(int $cajaId, array $scope): Caja
    {
        $caja = Caja::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->where('estado', Caja::ABIERTA)
            ->lockForUpdate()
            ->findOrFail($cajaId);

        return $caja;
    }

    protected function crearMovimiento(Caja $caja, array $data): CajaMovimiento
    {
        return CajaMovimiento::create([
            'tenant_id' => $caja->tenant_id,
            'empresa_id' => $caja->empresa_id,
            'tienda_id' => $caja->tienda_id,
            'caja_id' => $caja->id,
            'user_id' => $data['user_id'],
            'tipo_movimiento' => $data['tipo_movimiento'],
            'metodo_pago' => $data['metodo_pago'],
            'concepto' => $data['concepto'],
            'monto' => $data['monto'],
            'referencia_tipo' => $data['referencia_tipo'] ?? null,
            'referencia_id' => $data['referencia_id'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'created_at' => now(),
        ]);
    }

    protected function cargarCaja(Caja $caja): Caja
    {
        return $caja->load(['userApertura', 'userCierre', 'movimientos.user']);
    }
}
