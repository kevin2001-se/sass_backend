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
use Illuminate\Validation\ValidationException;

class SunatInvoiceBuilder
{
    private const IGV = 0.18;

    public function buildFromVenta(Venta $venta): Invoice
    {
        $venta->loadMissing(['empresa.sunatConfiguraciones', 'cliente', 'detalles']);

        if ($venta->tipo_comprobante === Venta::FACTURA && (! $venta->cliente || $venta->cliente->tipo_documento !== Cliente::RUC)) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Para emitir FACTURA el cliente debe tener RUC.'],
            ]);
        }

        $cliente = $this->clienteGreenter($venta);
        $tipoDocumento = $venta->tipo_comprobante === Venta::FACTURA ? '01' : '03';
        $gravadas = round((float) $venta->detalles->where('afecto_igv', true)->sum('subtotal'), 2);
        $inafectas = round((float) $venta->detalles->where('afecto_igv', false)->sum('subtotal'), 2);

        return (new Invoice())
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
            return (new SaleDetail())
                ->setUnidad('NIU')
                ->setCantidad(round((float) $detalle->cantidad_presentacion, 4))
                ->setCodProducto((string) ($detalle->producto_id ?: ($index + 1)))
                ->setDescripcion($detalle->descripcion)
                ->setMtoBaseIgv(round((float) $detalle->subtotal, 2))
                ->setPorcentajeIgv($detalle->afecto_igv ? 18 : 0)
                ->setIgv(round((float) $detalle->igv, 2))
                ->setTipAfeIgv($detalle->afecto_igv ? '10' : '30')
                ->setTotalImpuestos(round((float) $detalle->igv, 2))
                ->setMtoValorVenta(round((float) $detalle->subtotal, 2))
                ->setMtoValorUnitario($detalle->afecto_igv
                    ? round((float) $detalle->precio_unitario / (1 + self::IGV), 6)
                    : round((float) $detalle->precio_unitario, 6))
                ->setMtoPrecioUnitario(round((float) $detalle->precio_unitario, 2));
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
}
