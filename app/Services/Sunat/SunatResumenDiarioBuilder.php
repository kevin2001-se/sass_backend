<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\ComprobanteElectronico;
use App\Models\ResumenDiario;
use App\Models\Venta;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Document;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;

class SunatResumenDiarioBuilder
{
    public function buildFromResumen(ResumenDiario $resumen): Summary
    {
        $resumen->loadMissing([
            'empresa.sunatConfiguraciones',
            'detalles.comprobanteElectronico.venta.cliente',
            'detalles.comprobanteElectronico.notaElectronica.venta.cliente',
            'detalles.comprobanteElectronico.notaElectronica.comprobanteReferencia',
        ]);

        return (new Summary())
            ->setFecGeneracion($resumen->fecha_resumen)
            ->setFecResumen($resumen->fecha_envio)
            ->setCorrelativo(str_pad((string) $resumen->correlativo, 3, '0', STR_PAD_LEFT))
            ->setMoneda('PEN')
            ->setCompany($this->empresaGreenter($resumen))
            ->setDetails($this->detallesGreenter($resumen));
    }

    protected function empresaGreenter(ResumenDiario $resumen): Company
    {
        $configuracion = $resumen->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($resumen->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $resumen->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $resumen->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $resumen->empresa->nombre)
            ->setAddress($address);
    }

    protected function detallesGreenter(ResumenDiario $resumen): array
    {
        return $resumen->detalles->values()->map(function ($detalle) {
            $comprobante = $detalle->comprobanteElectronico;
            $cliente = $this->clienteDelComprobante($comprobante);
            $subtotal = max(round((float) $detalle->total - (float) $detalle->total_igv, 2), 0);
            $summaryDetail = (new SummaryDetail())
                ->setTipoDoc($detalle->tipo_documento)
                ->setSerieNro($detalle->serie.'-'.$detalle->correlativo)
                ->setEstado($detalle->estado_item)
                ->setClienteTipo($this->tipoDocCliente($cliente))
                ->setClienteNro($cliente?->numero_documento ?: '00000000')
                ->setTotal(round((float) $detalle->total, 2))
                ->setMtoOperGravadas($subtotal)
                ->setMtoOperInafectas(0)
                ->setMtoOperExoneradas(0)
                ->setMtoOperExportacion(0)
                ->setMtoOperGratuitas(0)
                ->setMtoOtrosCargos(0)
                ->setPorcentajeIgv(18)
                ->setMtoIGV(round((float) $detalle->total_igv, 2))
                ->setMtoISC(0)
                ->setMtoOtrosTributos(0)
                ->setMtoIcbper(0);

            if (in_array($detalle->tipo_documento, ['07', '08'], true)) {
                $referencia = $comprobante->notaElectronica?->comprobanteReferencia;
                $summaryDetail->setDocReferencia((new Document())
                    ->setTipoDoc('03')
                    ->setNroDoc($referencia?->numero_comprobante));
            }

            return $summaryDetail;
        })->all();
    }

    protected function clienteDelComprobante(ComprobanteElectronico $comprobante): ?Cliente
    {
        if ($comprobante->tipo_comprobante === Venta::BOLETA) {
            return $comprobante->venta?->cliente;
        }

        return $comprobante->notaElectronica?->venta?->cliente;
    }

    protected function tipoDocCliente(?Cliente $cliente): string
    {
        return match ($cliente?->tipo_documento) {
            Cliente::RUC => '6',
            Cliente::DNI => '1',
            Cliente::CE => '4',
            default => '0',
        };
    }
}
