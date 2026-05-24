<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\Venta;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SunatInvoiceBuilder
{
    private const IGV = 0.18;

    public function buildFromVenta(Venta $venta): Invoice
    {
        $venta->loadMissing([
            'empresa.sunatConfiguraciones',
            'tienda',
            'cliente',
            'detalles.producto',
            'detalles.presentacion.unidadMedida',
        ]);

        if ($venta->tipo_comprobante === Venta::FACTURA && (! $venta->cliente || $venta->cliente->tipo_documento !== Cliente::RUC)) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Para emitir FACTURA el cliente debe tener RUC.'],
            ]);
        }

        $this->validarDetalles($venta);

        $cliente = $this->clienteGreenter($venta);
        $tipoDocumento = $venta->tipo_comprobante === Venta::FACTURA ? '01' : '03';
        $gravadas = round((float) $venta->detalles->where('afecto_igv', true)->sum('subtotal'), 2);
        $inafectas = round((float) $venta->detalles->where('afecto_igv', false)->sum('subtotal'), 2);
        $descuentos = round((float) $venta->detalles->sum('descuento'), 2);

        $invoice = (new Invoice())
            ->setUblVersion('2.0')
            ->setTipoOperacion('0101')
            ->setTipoDoc($tipoDocumento)
            ->setSerie($venta->serie)
            ->setCorrelativo((string) $venta->correlativo)
            ->setFechaEmision($venta->fecha_emision)
            ->setTipoMoneda('PEN')
            ->setCompany($this->empresaGreenter($venta))
            ->setClient($cliente)
            ->setMtoOperGravadas($gravadas > 0 ? $gravadas : null)
            ->setMtoOperInafectas($inafectas > 0 ? $inafectas : null)
            ->setMtoIGV(round((float) $venta->total_igv, 2))
            ->setTotalImpuestos(round((float) $venta->total_igv, 2))
            ->setValorVenta(round((float) $venta->subtotal, 2))
            ->setSubTotal(round((float) $venta->total, 2))
            ->setMtoImpVenta(round((float) $venta->total, 2))
            ->setDetails($this->detallesGreenter($venta))
            ->setLegends($this->leyendasGreenter($venta));

        if ($descuentos > 0) {
            $invoice->setMtoDescuentos($descuentos);
        }

        return $invoice;
    }

    protected function empresaGreenter(Venta $venta): Company
    {
        $configuracion = $venta->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($venta->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $venta->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $venta->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $venta->empresa->nombre)
            ->setAddress($address);
    }

    protected function clienteGreenter(Venta $venta): Client
    {
        if (! $venta->cliente && $venta->tipo_comprobante === Venta::BOLETA) {
            return (new Client())
                ->setTipoDoc('0')
                ->setNumDoc('00000000')
                ->setRznSocial('CLIENTES VARIOS');
        }

        return (new Client())
            ->setTipoDoc($this->tipoDocumentoSunat($venta->cliente))
            ->setNumDoc($venta->cliente->numero_documento ?: '00000000')
            ->setRznSocial($venta->cliente->razon_social ?: $venta->cliente->nombres);
    }

    /**
     * @return SaleDetail[]
     */
    protected function detallesGreenter(Venta $venta): array
    {
        return $venta->detalles->values()->map(function ($detalle, int $index) {
            $descuento = round((float) $detalle->descuento, 2);
            $precioUnitario = round((float) $detalle->precio_unitario, 2);
            $cantidad = round((float) $detalle->cantidad_presentacion, 4);
            $subtotalBruto = round($cantidad * $precioUnitario, 2);
            $unidadSunat = $this->unidadSunat($detalle);

            $item = (new SaleDetail())
                ->setUnidad($unidadSunat)
                ->setCantidad($cantidad)
                ->setCodProducto((string) ($detalle->producto_id ?: ($index + 1)))
                ->setDescripcion($detalle->descripcion)
                ->setMtoBaseIgv(round((float) $detalle->subtotal, 2))
                ->setPorcentajeIgv($detalle->afecto_igv ? 18 : 0)
                ->setIgv(round((float) $detalle->igv, 2))
                ->setTipAfeIgv($detalle->afecto_igv ? '10' : '30')
                ->setTotalImpuestos(round((float) $detalle->igv, 2))
                ->setMtoValorVenta(round((float) $detalle->subtotal, 2))
                ->setMtoValorUnitario($detalle->afecto_igv
                    ? round($precioUnitario / (1 + self::IGV), 6)
                    : round($precioUnitario, 6))
                ->setMtoPrecioUnitario($precioUnitario);

            if ($descuento > 0) {
                $item->setDescuento($descuento);
            }

            Log::debug('SUNAT detalle invoice', [
                'venta_detalle_id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'unidad_sunat' => $unidadSunat,
                'subtotal_bruto' => $subtotalBruto,
                'descuento' => $descuento,
                'subtotal_guardado' => (float) $detalle->subtotal,
                'igv_guardado' => (float) $detalle->igv,
                'total_guardado' => (float) $detalle->total,
            ]);

            return $item;
        })->all();
    }

    /**
     * @return Legend[]
     */
    protected function leyendasGreenter(Venta $venta): array
    {
        return [
            (new Legend())
                ->setCode('1000')
                ->setValue('SON '.number_format((float) $venta->total, 2, '.', '').' SOLES'),
        ];
    }

    protected function tipoDocumentoSunat(Cliente $cliente): string
    {
        return match ($cliente->tipo_documento) {
            Cliente::RUC => '6',
            Cliente::DNI => '1',
            Cliente::CE => '4',
            default => '0',
        };
    }

    protected function unidadSunat($detalle): string
    {
        $codigo = strtoupper(trim((string) ($detalle->presentacion?->unidadMedida?->codigo_sunat ?: '')));

        if ($codigo === '') {
            Log::warning('SUNAT invoice detalle sin codigo_sunat, usando NIU.', [
                'venta_detalle_id' => $detalle->id,
                'producto_presentacion_id' => $detalle->producto_presentacion_id,
            ]);

            return 'NIU';
        }

        return $codigo;
    }

    protected function validarDetalles(Venta $venta): void
    {
        $sumTotal = 0;
        $sumIgv = 0;

        foreach ($venta->detalles as $detalle) {
            $cantidad = (float) $detalle->cantidad_presentacion;
            $precioUnitario = (float) $detalle->precio_unitario;
            $descuento = (float) $detalle->descuento;
            $subtotalBruto = round($cantidad * $precioUnitario, 2);
            $unidad = $this->unidadSunat($detalle);

            if ($unidad === '') {
                throw ValidationException::withMessages(['detalles' => ['Hay un detalle sin unidad SUNAT válida.']]);
            }

            if ($descuento < 0 || $descuento > $subtotalBruto) {
                Log::warning('SUNAT invoice descuento invalido.', [
                    'venta_id' => $venta->id,
                    'detalle_id' => $detalle->id,
                    'subtotal_bruto' => $subtotalBruto,
                    'descuento' => $descuento,
                ]);

                throw ValidationException::withMessages(['detalles' => ['El descuento de un item no puede superar su subtotal bruto.']]);
            }

            $sumTotal += (float) $detalle->total;
            $sumIgv += (float) $detalle->igv;
        }

        if (abs(round($sumTotal, 2) - round((float) $venta->total, 2)) > 0.02) {
            throw ValidationException::withMessages(['total' => ['El total de detalles no cuadra con el total de la venta.']]);
        }

        if (abs(round($sumIgv, 2) - round((float) $venta->total_igv, 2)) > 0.02) {
            throw ValidationException::withMessages(['total_igv' => ['El IGV de detalles no cuadra con el IGV de la venta.']]);
        }
    }
}