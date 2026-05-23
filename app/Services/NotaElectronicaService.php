<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\InventarioMovimiento;
use App\Models\NotaElectronica;
use App\Models\SerieComprobante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\Sunat\SunatClientFactory;
use App\Services\Sunat\SunatNoteBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotaElectronicaService
{
    private const IGV = 0.18;

    public function __construct(
        private readonly InventarioService $inventarioService,
        private readonly CajaService $cajaService,
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatNoteBuilder $noteBuilder
    ) {
    }

    public function crearNotaCredito(array $data): NotaElectronica
    {
        $nota = DB::transaction(function () use ($data) {
            $referencia = $this->validarComprobanteReferencia($data, NotaElectronica::NOTA_CREDITO);
            $detalles = $this->calcularDetallesCredito($referencia->venta, $data);
            $totales = $this->calcularTotales($detalles);
            $numero = $this->generarNumeroNota(NotaElectronica::NOTA_CREDITO, $data['tienda_id']);

            $nota = NotaElectronica::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'venta_id' => $referencia->venta_id,
                'comprobante_referencia_id' => $referencia->id,
                'tipo_nota' => NotaElectronica::NOTA_CREDITO,
                'serie' => $numero['serie'],
                'correlativo' => $numero['correlativo'],
                'numero_comprobante' => $numero['numero_comprobante'],
                'motivo_codigo' => $data['motivo_codigo'],
                'motivo_descripcion' => $data['motivo_descripcion'],
                'fecha_emision' => now(),
                'moneda' => 'PEN',
                'subtotal' => $totales['subtotal'],
                'total_igv' => $totales['igv'],
                'total' => $totales['total'],
                'estado' => NotaElectronica::REGISTRADA,
                'afecta_stock' => (bool) ($data['afecta_stock'] ?? false),
                'afecta_caja' => (bool) ($data['afecta_caja'] ?? false),
                'observacion' => $data['observacion'] ?? null,
            ]);

            $nota->detalles()->createMany($detalles);
            $this->crearComprobanteElectronicoNota($nota);

            return $nota;
        });

        $nota = $this->enviarNotaConGreenter($nota->refresh());

        if ($nota->comprobanteElectronico?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
            $this->aplicarEfectosNotaCredito($nota);
        }

        return $this->cargarNota($nota->refresh());
    }

    public function crearNotaDebito(array $data): NotaElectronica
    {
        $nota = DB::transaction(function () use ($data) {
            $referencia = $this->validarComprobanteReferencia($data, NotaElectronica::NOTA_DEBITO);
            $detalles = $this->calcularDetallesDebito($data);
            $totales = $this->calcularTotales($detalles);
            $numero = $this->generarNumeroNota(NotaElectronica::NOTA_DEBITO, $data['tienda_id']);

            $nota = NotaElectronica::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'venta_id' => $referencia->venta_id,
                'comprobante_referencia_id' => $referencia->id,
                'tipo_nota' => NotaElectronica::NOTA_DEBITO,
                'serie' => $numero['serie'],
                'correlativo' => $numero['correlativo'],
                'numero_comprobante' => $numero['numero_comprobante'],
                'motivo_codigo' => $data['motivo_codigo'],
                'motivo_descripcion' => $data['motivo_descripcion'],
                'fecha_emision' => now(),
                'moneda' => 'PEN',
                'subtotal' => $totales['subtotal'],
                'total_igv' => $totales['igv'],
                'total' => $totales['total'],
                'estado' => NotaElectronica::REGISTRADA,
                'afecta_stock' => false,
                'afecta_caja' => (bool) ($data['afecta_caja'] ?? false),
                'observacion' => $data['observacion'] ?? null,
            ]);

            $nota->detalles()->createMany($detalles);
            $this->crearComprobanteElectronicoNota($nota);

            return $nota;
        });

        $nota = $this->enviarNotaConGreenter($nota->refresh());

        if ($nota->comprobanteElectronico?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
            $this->aplicarEfectosNotaDebito($nota);
        }

        return $this->cargarNota($nota->refresh());
    }

    public function crearComprobanteElectronicoNota(NotaElectronica $nota): ComprobanteElectronico
    {
        return ComprobanteElectronico::create([
            'tenant_id' => $nota->tenant_id,
            'empresa_id' => $nota->empresa_id,
            'tienda_id' => $nota->tienda_id,
            'venta_id' => $nota->venta_id,
            'nota_electronica_id' => $nota->id,
            'documento_origen_tipo' => $nota->tipo_nota,
            'documento_origen_id' => $nota->id,
            'tipo_comprobante' => $nota->tipo_nota,
            'serie' => $nota->serie,
            'correlativo' => $nota->correlativo,
            'numero_comprobante' => $nota->numero_comprobante,
            'fecha_emision' => $nota->fecha_emision,
            'moneda' => $nota->moneda,
            'estado_sunat' => ComprobanteElectronico::PENDIENTE,
        ]);
    }

    public function enviarNotaConGreenter(NotaElectronica $nota): NotaElectronica
    {
        $nota->loadMissing(['comprobanteElectronico', 'empresa.sunatConfiguraciones', 'venta.cliente', 'detalles', 'comprobanteReferencia']);
        $comprobante = $nota->comprobanteElectronico;

        try {
            $configuracion = $nota->empresa->sunatConfiguraciones->firstWhere('estado', true);

            if (! $configuracion) {
                throw ValidationException::withMessages(['sunat_configuracion' => ['No existe configuraciÃ³n SUNAT activa.']]);
            }

            $see = $this->clientFactory->make($configuracion);
            $note = $this->noteBuilder->buildFromNota($nota);
            $xml = $see->getXmlSigned($note);

            if (! $xml) {
                throw ValidationException::withMessages(['sunat' => ['Greenter no pudo generar el XML firmado de la nota.']]);
            }

            $this->guardarXmlFirmado($comprobante, $xml);
            $response = $see->sendXml($note::class, $note->getName(), $xml);

            if (! $response) {
                throw ValidationException::withMessages(['sunat' => ['SUNAT no devolviÃ³ respuesta para la nota.']]);
            }

            $this->actualizarEstadoSunat($comprobante->refresh(), $response);
        } catch (Throwable $e) {
            $comprobante->increment('intentos_envio');
            $comprobante->update([
                'estado_sunat' => ComprobanteElectronico::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo enviar la nota a SUNAT. '.$e->getMessage()]]);
        }

        return $nota->refresh();
    }

    public function reenviarNota(int $notaId, array $scope): NotaElectronica
    {
        $nota = NotaElectronica::where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])->findOrFail($notaId);

        if ($nota->comprobanteElectronico?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['nota' => ['La nota ya fue aceptada por SUNAT.']]);
        }

        $nota = $this->cargarNota($this->enviarNotaConGreenter($nota));

        if ($nota->comprobanteElectronico?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
            if ($nota->tipo_nota === NotaElectronica::NOTA_CREDITO) {
                $this->aplicarEfectosNotaCredito($nota);
            } else {
                $this->aplicarEfectosNotaDebito($nota);
            }
        }

        return $nota;
    }

    public function anularNota(int $notaId, string $motivo, array $scope): NotaElectronica
    {
        return DB::transaction(function () use ($notaId, $motivo, $scope) {
            $nota = NotaElectronica::where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])->lockForUpdate()->findOrFail($notaId);

            if ($nota->estado === NotaElectronica::ANULADA) {
                throw ValidationException::withMessages(['nota' => ['La nota ya estÃ¡ anulada.']]);
            }

            $nota->update([
                'estado' => NotaElectronica::ANULADA,
                'observacion' => trim(($nota->observacion ? $nota->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);

            return $this->cargarNota($nota->refresh());
        });
    }

    public function aplicarEfectosNotaCredito(NotaElectronica $nota): void
    {
        $nota->loadMissing(['detalles', 'venta.pagos']);

        if ($nota->afecta_stock) {
            foreach ($nota->detalles as $detalle) {
                if (! $detalle->producto_id || ! $detalle->producto_presentacion_id) {
                    continue;
                }

                $this->inventarioService->aumentarStock([
                    'tenant_id' => $nota->tenant_id,
                    'empresa_id' => $nota->empresa_id,
                    'tienda_id' => $nota->tienda_id,
                    'producto_id' => $detalle->producto_id,
                    'producto_presentacion_id' => $detalle->producto_presentacion_id,
                    'lote_id' => $detalle->lote_id,
                    'cantidad_presentacion' => $detalle->cantidad_presentacion,
                    'motivo' => 'Nota de crÃ©dito '.$nota->numero_comprobante,
                    'tipo_movimiento' => InventarioMovimiento::DEVOLUCION,
                    'referencia_tipo' => 'NOTA_CREDITO',
                    'referencia_id' => $nota->id,
                    'observacion' => $nota->observacion,
                    'user_id' => $nota->venta->user_id,
                ]);
            }
        }

        if ($nota->afecta_caja) {
            $this->registrarEgresoCajaPorNotaCredito($nota);
        }
    }

    public function aplicarEfectosNotaDebito(NotaElectronica $nota): void
    {
        if (! $nota->afecta_caja) {
            return;
        }

        $this->cajaService->registrarIngreso([
            'tenant_id' => $nota->tenant_id,
            'empresa_id' => $nota->empresa_id,
            'tienda_id' => $nota->tienda_id,
            'user_id' => $nota->venta->user_id,
            'metodo_pago' => 'EFECTIVO',
            'concepto' => 'Nota de dÃ©bito '.$nota->numero_comprobante,
            'monto' => $nota->total,
            'referencia_tipo' => 'NOTA_DEBITO',
            'referencia_id' => $nota->id,
            'observacion' => $nota->observacion,
        ]);
    }

    public function generarNumeroNota(string $tipoNota, int $tiendaId): array
    {
        $serie = SerieComprobante::where('tienda_id', $tiendaId)
            ->where('tipo_comprobante', $tipoNota)
            ->where('estado', true)
            ->lockForUpdate()
            ->first();

        if (! $serie) {
            throw ValidationException::withMessages(['tipo_nota' => ['No existe serie activa para '.$tipoNota.' en la tienda activa.']]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [
            'serie' => $serie->serie,
            'correlativo' => $correlativo,
            'numero_comprobante' => $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        ];
    }

    protected function validarComprobanteReferencia(array $data, string $tipoNota): ComprobanteElectronico
    {
        $referencia = ComprobanteElectronico::with(['venta.detalles', 'venta.pagos', 'venta.cliente'])
            ->where('tenant_id', $data['tenant_id'])
            ->where('empresa_id', $data['empresa_id'])
            ->where('tienda_id', $data['tienda_id'])
            ->where('estado_sunat', ComprobanteElectronico::ACEPTADO)
            ->findOrFail($data['comprobante_referencia_id']);

        if (! in_array($referencia->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_referencia_id' => ['La nota solo puede referenciar BOLETA o FACTURA aceptada.']]);
        }

        if (! $referencia->venta || $referencia->venta->estado !== Venta::REGISTRADA) {
            throw ValidationException::withMessages(['venta' => ['La venta relacionada no estÃ¡ registrada.']]);
        }

        return $referencia;
    }

    protected function calcularDetallesCredito(Venta $venta, array $data): array
    {
        $items = collect($data['detalles'] ?? []);

        if ($items->isEmpty()) {
            $items = $venta->detalles->map(fn ($detalle) => [
                'venta_detalle_id' => $detalle->id,
                'cantidad_presentacion' => $detalle->cantidad_presentacion,
            ]);
        }

        return $items->map(function ($item) use ($venta) {
            $detalle = $venta->detalles->firstWhere('id', (int) $item['venta_detalle_id']);

            if (! $detalle) {
                throw ValidationException::withMessages(['detalles' => ['El detalle no pertenece a la venta original.']]);
            }

            $cantidad = round((float) $item['cantidad_presentacion'], 4);

            if ($cantidad > (float) $detalle->cantidad_presentacion) {
                throw ValidationException::withMessages(['cantidad_presentacion' => ['La cantidad de la nota supera la cantidad vendida.']]);
            }

            return $this->detalleDesdeVentaDetalle($detalle, $cantidad);
        })->all();
    }

    protected function calcularDetallesDebito(array $data): array
    {
        return collect($data['detalles'])->map(function ($detalle) use ($data) {
            $cantidad = round((float) $detalle['cantidad_presentacion'], 4);
            $precio = round((float) $detalle['precio_unitario'], 2);
            $total = round($cantidad * $precio, 2);
            $afectoIgv = (bool) ($detalle['afecto_igv'] ?? true);
            $subtotal = $afectoIgv ? round($total / (1 + self::IGV), 2) : $total;
            $igv = $afectoIgv ? round($total - $subtotal, 2) : 0;

            return [
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'descripcion' => $detalle['descripcion'],
                'cantidad_presentacion' => $cantidad,
                'factor_conversion' => 1,
                'cantidad_base' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
            ];
        })->all();
    }

    protected function detalleDesdeVentaDetalle(VentaDetalle $detalle, float $cantidad): array
    {
        $total = round($cantidad * (float) $detalle->precio_unitario, 2);
        $subtotal = round($total / (1 + self::IGV), 2);
        $igv = round($total - $subtotal, 2);

        return [
            'tenant_id' => $detalle->tenant_id,
            'empresa_id' => $detalle->empresa_id,
            'venta_detalle_id' => $detalle->id,
            'producto_id' => $detalle->producto_id,
            'producto_presentacion_id' => $detalle->producto_presentacion_id,
            'lote_id' => $detalle->lote_id,
            'descripcion' => $detalle->descripcion,
            'cantidad_presentacion' => $cantidad,
            'factor_conversion' => $detalle->factor_conversion,
            'cantidad_base' => round($cantidad * (float) $detalle->factor_conversion, 4),
            'precio_unitario' => $detalle->precio_unitario,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    protected function calcularTotales(array $detalles): array
    {
        return [
            'subtotal' => round(collect($detalles)->sum('subtotal'), 2),
            'igv' => round(collect($detalles)->sum('igv'), 2),
            'total' => round(collect($detalles)->sum('total'), 2),
        ];
    }

    protected function registrarEgresoCajaPorNotaCredito(NotaElectronica $nota): void
    {
        $pagos = $nota->venta->pagos->where('metodo_pago', '!=', 'CREDITO');
        $totalVenta = max((float) $nota->venta->total, 0.01);

        foreach ($pagos as $pago) {
            $monto = round(((float) $pago->monto / $totalVenta) * (float) $nota->total, 2);

            if ($monto <= 0) {
                continue;
            }

            $this->cajaService->registrarEgreso([
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
                'user_id' => $nota->venta->user_id,
                'metodo_pago' => $pago->metodo_pago,
                'concepto' => 'Nota de crÃ©dito '.$nota->numero_comprobante,
                'monto' => $monto,
                'referencia_tipo' => 'NOTA_CREDITO',
                'referencia_id' => $nota->id,
                'observacion' => $nota->observacion,
            ]);
        }
    }

    protected function guardarXmlFirmado(ComprobanteElectronico $comprobante, string $xml): void
    {
        Storage::disk('local')->put($this->xmlPath($comprobante), $xml);
        $comprobante->update([
            'xml_path' => $this->xmlPath($comprobante),
            'hash' => hash('sha256', $xml),
        ]);
    }

    protected function guardarCdr(ComprobanteElectronico $comprobante, mixed $cdr): void
    {
        if (! $cdr) {
            return;
        }

        Storage::disk('local')->put($this->cdrPath($comprobante), is_string($cdr) ? $cdr : (string) $cdr);
        $comprobante->update(['cdr_path' => $this->cdrPath($comprobante)]);
    }

    protected function actualizarEstadoSunat(ComprobanteElectronico $comprobante, mixed $response): void
    {
        $cdrResponse = method_exists($response, 'getCdrResponse') ? $response->getCdrResponse() : null;
        $error = method_exists($response, 'getError') ? $response->getError() : null;
        $codigo = $cdrResponse?->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Respuesta SUNAT recibida.';
        $cdr = method_exists($response, 'getCdrZip') ? $response->getCdrZip() : null;
        $aceptado = ($cdrResponse && $cdrResponse->isAccepted()) || (method_exists($response, 'isSuccess') && $response->isSuccess());

        $this->guardarCdr($comprobante, $cdr);
        $comprobante->increment('intentos_envio');
        $comprobante->update([
            'estado_sunat' => $aceptado ? ComprobanteElectronico::ACEPTADO : ComprobanteElectronico::RECHAZADO,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'enviado_at' => now(),
            'aceptado_at' => $aceptado ? now() : null,
            'rechazado_at' => $aceptado ? null : now(),
        ]);
    }

    protected function xmlPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/comprobantes/'.$comprobante->empresa_id.'/'.$comprobante->tipo_comprobante.'/'.$comprobante->fecha_emision->format('Y-m-d').'/xml/'.$comprobante->numero_comprobante.'.xml';
    }

    protected function cdrPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/comprobantes/'.$comprobante->empresa_id.'/'.$comprobante->tipo_comprobante.'/'.$comprobante->fecha_emision->format('Y-m-d').'/cdr/R-'.$comprobante->numero_comprobante.'.zip';
    }

    protected function cargarNota(NotaElectronica $nota): NotaElectronica
    {
        return $nota->load(['detalles', 'venta.cliente', 'comprobanteReferencia', 'comprobanteElectronico']);
    }
}
