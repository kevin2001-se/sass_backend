<?php

namespace App\Services\Sunat;

use App\Models\Cliente as ClienteModel;
use App\Models\NotaElectronica;
use App\Models\Venta;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;

class SunatNoteBuilder
{
    private const IGV = 0.18;

    public function buildFromNota(NotaElectronica $nota): Note
    {
        $nota->loadMissing([
            'empresa.sunatConfiguraciones',
            'venta.cliente',
            'detalles',
            'comprobanteReferencia',
        ]);

        $tipoNotaSunat = $nota->tipo_nota === NotaElectronica::NOTA_CREDITO ? '07' : '08';
        $tipoDocAfectado = $nota->comprobanteReferencia->tipo_comprobante === Venta::FACTURA ? '01' : '03';

        return (new Note())
            ->setUblVersion('2.1')
            ->setTipoDoc($tipoNotaSunat)
            ->setSerie($nota->serie)
            ->setCorrelativo((string) $nota->correlativo)
            ->setFechaEmision($nota->fecha_emision)
            ->setTipDocAfectado($tipoDocAfectado)
            ->setNumDocfectado($nota->comprobanteReferencia->numero_comprobante)
            ->setCodMotivo($nota->motivo_codigo)
            ->setDesMotivo($nota->motivo_descripcion)
            ->setTipoMoneda('PEN')
            ->setCompany($this->empresaGreenter($nota))
            ->setClient($this->clienteGreenter($nota))
            ->setMtoOperGravadas(round((float) $nota->subtotal, 2))
            ->setMtoIGV(round((float) $nota->total_igv, 2))
            ->setTotalImpuestos(round((float) $nota->total_igv, 2))
            ->setValorVenta(round((float) $nota->subtotal, 2))
            ->setSubTotal(round((float) $nota->total, 2))
            ->setMtoImpVenta(round((float) $nota->total, 2))
            ->setDetails($this->detallesGreenter($nota))
            ->setLegends($this->leyendasGreenter($nota));
    }

    protected function empresaGreenter(NotaElectronica $nota): Company
    {
        $configuracion = $nota->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($nota->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $nota->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $nota->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $nota->empresa->nombre)
            ->setAddress($address);
    }

    protected function clienteGreenter(NotaElectronica $nota): Client
    {
        $cliente = $nota->venta->cliente;

        if (! $cliente) {
            return (new Client())->setTipoDoc('0')->setNumDoc('00000000')->setRznSocial('CLIENTES VARIOS');
        }

        return (new Client())
            ->setTipoDoc(match ($cliente->tipo_documento) {
                ClienteModel::RUC => '6',
                ClienteModel::DNI => '1',
                ClienteModel::CE => '4',
                default => '0',
            })
            ->setNumDoc($cliente->numero_documento ?: '00000000')
            ->setRznSocial($cliente->razon_social ?: $cliente->nombres);
    }

    protected function detallesGreenter(NotaElectronica $nota): array
    {
        return $nota->detalles->values()->map(function ($detalle, int $index) {
            return (new SaleDetail())
                ->setUnidad('NIU')
                ->setCantidad(round((float) $detalle->cantidad_presentacion, 4))
                ->setCodProducto((string) ($detalle->producto_id ?: ($index + 1)))
                ->setDescripcion($detalle->descripcion)
                ->setMtoBaseIgv(round((float) $detalle->subtotal, 2))
                ->setPorcentajeIgv(18)
                ->setIgv(round((float) $detalle->igv, 2))
                ->setTipAfeIgv('10')
                ->setTotalImpuestos(round((float) $detalle->igv, 2))
                ->setMtoValorVenta(round((float) $detalle->subtotal, 2))
                ->setMtoValorUnitario(round((float) $detalle->precio_unitario / (1 + self::IGV), 6))
                ->setMtoPrecioUnitario(round((float) $detalle->precio_unitario, 2));
        })->all();
    }

    protected function leyendasGreenter(NotaElectronica $nota): array
    {
        return [
            (new Legend())
                ->setCode('1000')
                ->setValue('SON '.number_format((float) $nota->total, 2, '.', '').' SOLES'),
        ];
    }
}
