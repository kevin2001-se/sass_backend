<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CuentaPorPagar;
use App\Models\InventarioMovimiento;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraService
{
    private const IGV = 0.18;

    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly CajaService $cajaService
    ) {
    }

    public function registrarCompra(array $data): Compra
    {
        return DB::transaction(function () use ($data) {
            $proveedor = Proveedor::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->findOrFail($data['proveedor_id']);
            $detalles = $this->calcularDetalles($data);
            $totales = $this->calcularTotales($detalles);
            $pagado = round(collect($data['pagos'] ?? [])->sum(fn ($pago) => (float) $pago['monto']), 2);

            if ($data['tipo_compra'] === Compra::CONTADO && abs($pagado - $totales['total']) > 0.009) {
                throw ValidationException::withMessages(['pagos' => ['En compra CONTADO la suma de pagos debe ser igual al total.']]);
            }

            if ($data['tipo_compra'] === Compra::CREDITO && $pagado > $totales['total']) {
                throw ValidationException::withMessages(['pagos' => ['El pago inicial no puede superar el total.']]);
            }

            $compra = Compra::create([
                'tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'tienda_id' => $data['tienda_id'],
                'proveedor_id' => $proveedor->id, 'user_id' => $data['user_id'],
                'tipo_comprobante' => $data['tipo_comprobante'], 'serie' => $data['serie'], 'numero' => $data['numero'],
                'tipo_compra' => $data['tipo_compra'], 'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal' => $totales['subtotal'], 'total_igv' => $totales['igv'], 'total_descuento' => $totales['descuento'], 'total' => $totales['total'],
                'estado' => Compra::REGISTRADA, 'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($detalles as $detalle) {
                $compra->detalles()->create($detalle);
                $this->inventarioService->aumentarStock([
                    'tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'tienda_id' => $data['tienda_id'],
                    'producto_id' => $detalle['producto_id'], 'producto_presentacion_id' => $detalle['producto_presentacion_id'], 'lote_id' => $detalle['lote_id'],
                    'cantidad_presentacion' => $detalle['cantidad_presentacion'], 'motivo' => 'Compra '.$compra->serie.'-'.$compra->numero,
                    'tipo_movimiento' => InventarioMovimiento::COMPRA, 'referencia_tipo' => 'COMPRA', 'referencia_id' => $compra->id,
                    'observacion' => $data['observacion'] ?? null, 'user_id' => $data['user_id'],
                ]);
            }

            foreach (($data['pagos'] ?? []) as $pago) {
                $compra->pagos()->create(['tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'metodo_pago' => $pago['metodo_pago'], 'monto' => $pago['monto'], 'referencia' => $pago['referencia'] ?? null, 'estado' => 'REGISTRADO']);
            }

            if ($data['tipo_compra'] === Compra::CONTADO) {
                foreach (($data['pagos'] ?? []) as $pago) {
                    $this->cajaService->registrarEgreso([
                        'tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'tienda_id' => $data['tienda_id'], 'user_id' => $data['user_id'],
                        'metodo_pago' => $pago['metodo_pago'], 'concepto' => 'Compra '.$compra->serie.'-'.$compra->numero, 'monto' => $pago['monto'],
                        'referencia_tipo' => 'COMPRA', 'referencia_id' => $compra->id, 'observacion' => $pago['referencia'] ?? null,
                    ]);
                }
            } else {
                CuentaPorPagar::create([
                    'tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'tienda_id' => $data['tienda_id'], 'proveedor_id' => $proveedor->id, 'compra_id' => $compra->id,
                    'monto_total' => $totales['total'], 'monto_pagado' => $pagado, 'saldo' => round($totales['total'] - $pagado, 2),
                    'fecha_emision' => $data['fecha_emision'], 'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                    'estado' => $pagado <= 0 ? CuentaPorPagar::PENDIENTE : ($pagado >= $totales['total'] ? CuentaPorPagar::PAGADA : CuentaPorPagar::PARCIAL),
                ]);
            }

            return $this->cargarCompra($compra->refresh());
        });
    }

    public function anularCompra(int $compraId, string $motivo, array $scope): Compra
    {
        return DB::transaction(function () use ($compraId, $motivo, $scope) {
            $compra = Compra::with(['detalles', 'pagos', 'cuentaPorPagar'])->where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])->lockForUpdate()->findOrFail($compraId);
            if ($compra->estado === Compra::ANULADA) {
                throw ValidationException::withMessages(['compra' => ['La compra ya estÃ¡ anulada.']]);
            }

            foreach ($compra->detalles as $detalle) {
                $this->inventarioService->disminuirStock([
                    'tenant_id' => $compra->tenant_id, 'empresa_id' => $compra->empresa_id, 'tienda_id' => $compra->tienda_id,
                    'producto_id' => $detalle->producto_id, 'producto_presentacion_id' => $detalle->producto_presentacion_id, 'lote_id' => $detalle->lote_id,
                    'cantidad_presentacion' => $detalle->cantidad_presentacion, 'motivo' => 'AnulaciÃ³n de compra '.$compra->serie.'-'.$compra->numero,
                    'tipo_movimiento' => InventarioMovimiento::AJUSTE_NEGATIVO, 'referencia_tipo' => 'COMPRA', 'referencia_id' => $compra->id,
                    'observacion' => $motivo, 'user_id' => $scope['user_id'],
                ]);
            }

            if ($compra->tipo_compra === Compra::CONTADO) {
                foreach ($compra->pagos as $pago) {
                    $this->cajaService->registrarIngreso([
                        'tenant_id' => $compra->tenant_id, 'empresa_id' => $compra->empresa_id, 'tienda_id' => $compra->tienda_id, 'user_id' => $scope['user_id'],
                        'metodo_pago' => $pago->metodo_pago, 'concepto' => 'AnulaciÃ³n de compra '.$compra->serie.'-'.$compra->numero, 'monto' => $pago->monto,
                        'referencia_tipo' => 'COMPRA', 'referencia_id' => $compra->id, 'observacion' => $motivo,
                    ]);
                }
            }

            $compra->cuentaPorPagar?->update(['estado' => CuentaPorPagar::ANULADA, 'saldo' => 0]);
            $compra->pagos()->update(['estado' => 'ANULADO']);
            $compra->update(['estado' => Compra::ANULADA, 'observacion' => trim(($compra->observacion ? $compra->observacion.' | ' : '').'ANULADA: '.$motivo)]);

            return $this->cargarCompra($compra->refresh());
        });
    }

    protected function calcularDetalles(array $data): array
    {
        return collect($data['detalles'])->map(function (array $detalle) use ($data) {
            $producto = Producto::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->find($detalle['producto_id']);
            $presentacion = ProductoPresentacion::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->where('producto_id', $detalle['producto_id'])->find($detalle['producto_presentacion_id']);
            if (! $producto || ! $presentacion) {
                throw ValidationException::withMessages(['detalles' => ['Producto o presentaciÃ³n invÃ¡lidos.']]);
            }
            $lote = $this->resolverLote($data, $producto, $detalle);
            $cantidad = (float) $detalle['cantidad_presentacion'];
            $factor = (float) $presentacion->factor_conversion;
            $precio = (float) $detalle['precio_unitario'];
            $descuento = round((float) ($detalle['descuento'] ?? 0), 2);
            $bruto = round($cantidad * $precio, 2);
            if ($descuento > $bruto) {
                throw ValidationException::withMessages(['detalles.*.descuento' => ['El descuento no puede superar el importe del detalle.']]);
            }
            $total = round($bruto - $descuento, 2);
            $subtotal = $producto->afecto_igv ? round($total / (1 + self::IGV), 2) : $total;
            $igv = $producto->afecto_igv ? round($total - $subtotal, 2) : 0;
            return [
                'tenant_id' => $data['tenant_id'], 'empresa_id' => $data['empresa_id'], 'producto_id' => $producto->id, 'producto_presentacion_id' => $presentacion->id,
                'lote_id' => $lote?->id, 'descripcion' => trim($producto->nombre.' '.$presentacion->nombre), 'cantidad_presentacion' => $cantidad,
                'factor_conversion' => $factor, 'cantidad_base' => round($cantidad * $factor, 4), 'precio_unitario' => $precio, 'descuento' => $descuento,
                'afecto_igv' => $producto->afecto_igv, 'subtotal' => $subtotal, 'igv' => $igv, 'total' => $total,
            ];
        })->all();
    }

    protected function resolverLote(array $data, Producto $producto, array $detalle): ?Lote
    {
        if (! $producto->maneja_lote) {
            if (! empty($detalle['lote_id']) || ! empty($detalle['lote'])) {
                throw ValidationException::withMessages(['detalles.*.lote_id' => ['El producto no maneja lote.']]);
            }
            return null;
        }
        if (! empty($detalle['lote_id'])) {
            return Lote::where('tenant_id', $data['tenant_id'])->where('empresa_id', $data['empresa_id'])->where('producto_id', $producto->id)->findOrFail($detalle['lote_id']);
        }
        $loteData = $detalle['lote'] ?? null;
        if (! $loteData || empty($loteData['codigo_lote'])) {
            throw ValidationException::withMessages(['detalles.*.lote' => ['El lote es obligatorio para este producto.']]);
        }
        if ($producto->maneja_vencimiento && empty($loteData['fecha_vencimiento'])) {
            throw ValidationException::withMessages(['detalles.*.lote.fecha_vencimiento' => ['La fecha de vencimiento es obligatoria.']]);
        }
        return Lote::firstOrCreate([
            'empresa_id' => $data['empresa_id'], 'producto_id' => $producto->id, 'codigo_lote' => $loteData['codigo_lote'],
        ], [
            'tenant_id' => $data['tenant_id'], 'fecha_vencimiento' => $loteData['fecha_vencimiento'] ?? null, 'estado' => true,
        ]);
    }

    protected function calcularTotales(array $detalles): array
    {
        return ['subtotal' => round(collect($detalles)->sum('subtotal'), 2), 'igv' => round(collect($detalles)->sum('igv'), 2), 'descuento' => round(collect($detalles)->sum('descuento'), 2), 'total' => round(collect($detalles)->sum('total'), 2)];
    }

    protected function cargarCompra(Compra $compra): Compra
    {
        return $compra->load(['proveedor', 'user', 'detalles.producto', 'detalles.presentacion.unidadMedida', 'detalles.lote', 'pagos', 'cuentaPorPagar.pagos']);
    }
}



