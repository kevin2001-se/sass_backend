<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ComprobanteElectronico;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\SerieComprobante;
use App\Models\Tienda;
use App\Models\Venta;
use App\Services\Sunat\ComprobanteElectronicoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VentaService
{
    private const IGV = 0.18;

    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly CajaService $cajaService,
        private readonly CuentaPorCobrarService $cuentaPorCobrarService,
        private readonly ComprobanteElectronicoService $comprobanteElectronicoService
    )
    {
    }

    public function registrarVenta(array $data): Venta
    {
        $venta = DB::transaction(function () use ($data) {
            $permitirVentaSinStock = (bool) \parametro('permitir_venta_sin_stock', false);
            $detallesCalculados = $this->calcularDetalles($data);

            if ($permitirVentaSinStock) {
                Log::info('Parametro permitir_venta_sin_stock activo para registro de venta.', [
                    'tenant_id' => $data['tenant_id'],
                    'empresa_id' => $data['empresa_id'],
                    'tienda_id' => $data['tienda_id'],
                    'tipo_comprobante' => $data['tipo_comprobante'],
                ]);
            }
            $totales = $this->calcularTotales($detallesCalculados);
            $cliente = $this->validarCliente($data, $totales['total']);
            $totalPagado = $this->validarPagos($data, $totales['total']);
            $saldoPendiente = round(max((float) $totales['total'] - $totalPagado, 0), 2);
            $numero = $this->generarNumeroComprobante($data['tipo_comprobante'], $data['tienda_id']);

            $venta = Venta::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'cliente_id' => $cliente?->id,
                'user_id' => $data['user_id'],
                'tipo_comprobante' => $data['tipo_comprobante'],
                'serie' => $numero['serie'],
                'correlativo' => $numero['correlativo'],
                'numero_comprobante' => $numero['numero_comprobante'],
                'tipo_venta' => $data['tipo_venta'],
                'fecha_emision' => now(),
                'subtotal' => $totales['subtotal'],
                'total_igv' => $totales['igv'],
                'total_descuento' => $totales['descuento'],
                'total' => $totales['total'],
                'monto_pagado' => $totalPagado,
                'saldo_pendiente' => $saldoPendiente,
                'estado' => Venta::REGISTRADA,
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($detallesCalculados as $detalle) {
                $venta->detalles()->create($detalle);

                $this->inventarioService->disminuirStock([
                    'tenant_id' => $data['tenant_id'],
                    'empresa_id' => $data['empresa_id'],
                    'tienda_id' => $data['tienda_id'],
                    'producto_id' => $detalle['producto_id'],
                    'producto_presentacion_id' => $detalle['producto_presentacion_id'],
                    'lote_id' => $detalle['lote_id'],
                    'cantidad_presentacion' => $detalle['cantidad_presentacion'],
                    'motivo' => 'Venta '.$venta->numero_comprobante,
                    'tipo_movimiento' => InventarioMovimiento::VENTA,
                    'referencia_tipo' => 'VENTA',
                    'referencia_id' => $venta->id,
                    'observacion' => $data['observacion'] ?? null,
                    'user_id' => $data['user_id'],
                    'permitir_stock_negativo' => $permitirVentaSinStock,
                ]);
            }

            foreach (($data['pagos'] ?? []) as $pago) {
                $venta->pagos()->create([
                    'tenant_id' => $data['tenant_id'],
                    'empresa_id' => $data['empresa_id'],
                    'metodo_pago' => $pago['metodo_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'estado' => 'REGISTRADO',
                ]);
            }

            $venta->load('pagos');

            if ($venta->tipo_venta === Venta::CONTADO) {
                $this->cajaService->registrarMovimientoVenta($venta);
            }

            if ($venta->tipo_venta === Venta::CREDITO) {
                $this->cuentaPorCobrarService->crearDesdeVenta($venta);
            }

            return $this->cargarVenta($venta);
        });

        $this->emitirSunatAutomatico($venta);

        return $this->cargarVenta($venta->refresh());
    }

    public function anularVenta(int $ventaId, string $motivo, array $scope): Venta
    {
        return DB::transaction(function () use ($ventaId, $motivo, $scope) {
            $venta = Venta::with(['detalles', 'comprobanteElectronico'])
                ->where('tenant_id', $scope['tenant_id'])
                ->where('empresa_id', $scope['empresa_id'])
                ->where('tienda_id', $scope['tienda_id'])
                ->lockForUpdate()
                ->findOrFail($ventaId);

            if ($venta->estado === Venta::ANULADA) {
                throw ValidationException::withMessages([
                    'venta' => ['La venta ya esta anulada.'],
                ]);
            }

            if (in_array($venta->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)
                && $venta->comprobanteElectronico?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
                throw ValidationException::withMessages([
                    'venta' => ['Este comprobante fue aceptado por SUNAT. Debe generar una Nota de Credito.'],
                ]);
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
                    'motivo' => 'Anulacion de venta '.$venta->numero_comprobante,
                    'tipo_movimiento' => InventarioMovimiento::DEVOLUCION,
                    'referencia_tipo' => 'VENTA',
                    'referencia_id' => $venta->id,
                    'observacion' => $motivo,
                    'user_id' => $scope['user_id'],
                ]);
            }

            $venta->load('pagos');
            $this->cajaService->registrarAnulacionVenta($venta, $scope['user_id'], $motivo);
            $this->cuentaPorCobrarService->anularPorVenta($venta->id, $motivo);

            $venta->update([
                'estado' => Venta::ANULADA,
                'motivo_anulacion' => $motivo,
                'anulado_at' => now(),
                'anulado_by' => $scope['user_id'],
                'observacion' => trim(($venta->observacion ? $venta->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);
            $venta->pagos()->update(['estado' => 'ANULADO']);

            return $this->cargarVenta($venta->refresh());
        });
    }

    public function generarNumeroComprobante(string $tipoComprobante, int $tiendaId): array
    {
        $tienda = Tienda::with('empresa')->findOrFail($tiendaId);

        $serie = SerieComprobante::where('empresa_id', $tienda->empresa_id)
            ->where('tienda_id', $tiendaId)
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('estado', true)
            ->lockForUpdate()
            ->first();

        if (! $serie) {
            throw ValidationException::withMessages([
                'tipo_comprobante' => ['No existe una serie activa para este tipo de comprobante en la tienda.'],
            ]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [
            'serie' => $serie->serie,
            'correlativo' => $correlativo,
            'numero_comprobante' => $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        ];
    }

    protected function calcularDetalles(array $data): array
    {
        return collect($data['detalles'])->map(function (array $detalle) use ($data) {
            $producto = Producto::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->find($detalle['producto_id']);

            $presentacion = ProductoPresentacion::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('producto_id', $detalle['producto_id'])
                ->find($detalle['producto_presentacion_id']);

            if (! $producto || ! $presentacion) {
                throw ValidationException::withMessages([
                    'detalles' => ['Producto o presentacion invalidos.'],
                ]);
            }

            $cantidadPresentacion = (float) $detalle['cantidad_presentacion'];
            $factorConversion = (float) $presentacion->factor_conversion;
            $cantidadBase = round($cantidadPresentacion * $factorConversion, 4);
            $precioUnitario = (float) $presentacion->precio_venta;
            $descuento = round((float) ($detalle['descuento'] ?? 0), 2);
            $importeBruto = round($cantidadPresentacion * $precioUnitario, 2);

            if ($descuento > $importeBruto) {
                throw ValidationException::withMessages([
                    'detalles.*.descuento' => ['El descuento no puede superar el importe del detalle.'],
                ]);
            }

            $total = round($importeBruto - $descuento, 2);
            $aplicaIgvNotaVenta = (bool) \parametro('aplicar_igv_en_nota_venta', false);
            $aplicaIgv = ($data['tipo_comprobante'] !== Venta::NOTA_VENTA || $aplicaIgvNotaVenta) && (bool) $producto->afecto_igv;
            $subtotal = $aplicaIgv ? round($total / (1 + self::IGV), 2) : $total;
            $igv = $aplicaIgv ? round($total - $subtotal, 2) : 0;

            return [
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'producto_id' => $producto->id,
                'producto_presentacion_id' => $presentacion->id,
                'lote_id' => $detalle['lote_id'] ?? null,
                'descripcion' => trim($producto->nombre.' '.$presentacion->nombre),
                'cantidad_presentacion' => $cantidadPresentacion,
                'factor_conversion' => $factorConversion,
                'cantidad_base' => $cantidadBase,
                'precio_unitario' => $precioUnitario,
                'descuento' => $descuento,
                'afecto_igv' => $aplicaIgv,
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
            'descuento' => round(collect($detalles)->sum('descuento'), 2),
            'total' => round(collect($detalles)->sum('total'), 2),
        ];
    }

    protected function validarCliente(array $data, float $total): ?Cliente
    {
        $cliente = null;

        if (! empty($data['cliente_id'])) {
            $cliente = Cliente::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->find($data['cliente_id']);
        }

        if ($data['tipo_venta'] === Venta::CREDITO && (! $cliente || $cliente->tipo_documento === Cliente::SIN_DOCUMENTO || blank($cliente->numero_documento))) {


            throw ValidationException::withMessages([


                'cliente_id' => ['Para venta CREDITO debes seleccionar un cliente real con documento.'],


            ]);


        }



        if ($data['tipo_comprobante'] === Venta::FACTURA && (! $cliente || $cliente->tipo_documento !== Cliente::RUC)) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Para FACTURA el cliente es obligatorio y debe tener RUC.'],
            ]);
        }

        if ($data['tipo_comprobante'] === Venta::BOLETA && $total > 700 && (! $cliente || ! in_array($cliente->tipo_documento, [Cliente::DNI, Cliente::RUC], true))) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Para BOLETA mayor a S/ 700 el cliente debe tener DNI o RUC.'],
            ]);
        }

        return $cliente;
    }

    protected function validarPagos(array $data, float $total): float
    {
        $pagos = collect($data['pagos'] ?? []);
        $totalPagado = round($pagos->sum(fn ($pago) => (float) $pago['monto']), 2);

        if ($pagos->contains('metodo_pago', 'CREDITO')) {
            throw ValidationException::withMessages([
                'pagos' => ['No envies CREDITO como metodo de pago. Usa tipo_venta CREDITO para generar cuenta por cobrar.'],
            ]);
        }

        if ($data['tipo_venta'] === Venta::CONTADO && abs($totalPagado - $total) > 0.009) {
        Log::info('Validacion de pagos para venta CONTADO', [
            'total' => $total,
            'total_pagado' => $totalPagado,
            'diferencia' => abs($totalPagado - $total),
        ]);
            throw ValidationException::withMessages([
                'pagos' => ['En venta CONTADO la suma de pagos debe ser igual al total.'],
            ]);
        }

        if ($data['tipo_venta'] === Venta::CREDITO && $totalPagado > $total) {
            throw ValidationException::withMessages([
                'pagos' => ['En venta CREDITO el pago inicial no puede superar el total.'],
            ]);
        }

        return $totalPagado;
    }

    protected function cargarVenta(Venta $venta): Venta
    {
        return $venta->load([
            'cliente',
            'user',
            'detalles.producto',
            'detalles.presentacion.unidadMedida',
            'detalles.lote',
            'pagos',
            'cuentaPorCobrar.pagos',
            'comprobanteElectronico',
        ]);
    }

    protected function emitirSunatAutomatico(Venta $venta): void
    {
        if (! in_array($venta->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            return;
        }

        $scope = [
            'tenant_id' => $venta->tenant_id,
            'empresa_id' => $venta->empresa_id,
            'tienda_id' => $venta->tienda_id,
        ];
        $claveParametro = $venta->tipo_comprobante === Venta::BOLETA
            ? 'enviar_boleta_automaticamente'
            : 'enviar_factura_automaticamente';
        $enviarAutomaticamente = (bool) \parametro($claveParametro, false);

        try {
            if (! $enviarAutomaticamente) {
                $this->comprobanteElectronicoService->registrarPendienteDesdeVenta($venta->id, $scope);
                Log::info('Parametro SUNAT automatico desactivado: comprobante queda pendiente.', [
                    'venta_id' => $venta->id,
                    'tipo_comprobante' => $venta->tipo_comprobante,
                    'parametro' => $claveParametro,
                ]);
                return;
            }

            Log::info('Parametro SUNAT automatico activo: enviando comprobante.', [
                'venta_id' => $venta->id,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'parametro' => $claveParametro,
            ]);
            $this->comprobanteElectronicoService->emitirDesdeVenta($venta->id, $scope);
        } catch (ValidationException $e) {
            Log::warning('Emision SUNAT automatica fallida.', [
                'venta_id' => $venta->id,
                'errors' => $e->errors(),
            ]);
        }
    }
}

