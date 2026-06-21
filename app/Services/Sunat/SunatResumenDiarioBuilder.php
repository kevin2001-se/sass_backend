<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\ResumenDiario;
use App\Models\ResumenDiarioDetalle;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Document;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;

class SunatResumenDiarioBuilder
{
    public function buildFromResumen(ResumenDiario $resumen): Summary
    {
        $resumen->loadMissing(['empresa.sunatConfiguraciones', 'detalles']);

        return (new Summary())
            ->setFecGeneracion($resumen->fecha_resumen)
            ->setFecResumen($resumen->fecha_resumen)
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
        return $resumen->detalles->values()->map(function (ResumenDiarioDetalle $detalle) {
            $subtotal = round((float) ($detalle->subtotal ?: ((float) $detalle->total - (float) $detalle->total_igv)), 2);
            $summaryDetail = (new SummaryDetail())
                ->setTipoDoc($this->tipoDocumentoSunat($detalle->tipo_documento))
                ->setSerieNro($detalle->serie.'-'.$detalle->correlativo)
                ->setEstado($detalle->estado_item ?: ResumenDiarioDetalle::ADICIONAR)
                ->setClienteTipo($this->tipoDocCliente($detalle->cliente_tipo_documento))
                ->setClienteNro($detalle->cliente_numero_documento ?: '00000000')
                ->setTotal(round((float) $detalle->total, 2))
                ->setMtoOperGravadas(max($subtotal, 0))
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

            if (in_array($detalle->tipo_documento, [ResumenDiarioDetalle::NOTA_CREDITO, ResumenDiarioDetalle::NOTA_DEBITO], true)) {
                $referencia = $this->referenciaBoleta($detalle);
                $summaryDetail->setDocReferencia((new Document())
                    ->setTipoDoc('03')
                    ->setNroDoc($referencia ?: ''));
            }

            return $summaryDetail;
        })->all();
    }

    protected function tipoDocumentoSunat(string $tipo): string
    {
        return match ($tipo) {
            ResumenDiarioDetalle::BOLETA, '03' => '03',
            ResumenDiarioDetalle::NOTA_CREDITO, '07' => '07',
            ResumenDiarioDetalle::NOTA_DEBITO, '08' => '08',
            default => $tipo,
        };
    }

    protected function referenciaBoleta(ResumenDiarioDetalle $detalle): ?string
    {
        if ($detalle->tipo_documento === ResumenDiarioDetalle::NOTA_CREDITO) {
            return NotaCredito::with('comprobante')->find($detalle->documento_id)?->comprobante?->numero_comprobante;
        }

        if ($detalle->tipo_documento === ResumenDiarioDetalle::NOTA_DEBITO) {
            return NotaDebito::with('comprobante')->find($detalle->documento_id)?->comprobante?->numero_comprobante;
        }

        return null;
    }

    protected function tipoDocCliente(?string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            Cliente::RUC, 'RUC' => '6',
            Cliente::DNI, 'DNI' => '1',
            Cliente::CE, 'CE' => '4',
            default => '0',
        };
    }
}
