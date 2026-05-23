<?php

namespace App\Services\Sunat;

use App\Models\ComunicacionBaja;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;

class SunatComunicacionBajaBuilder
{
    public function buildFromBaja(ComunicacionBaja $baja): Voided
    {
        $baja->loadMissing(['empresa.sunatConfiguraciones', 'detalles']);

        return (new Voided())
            ->setFecGeneracion($baja->fecha_baja)
            ->setFecComunicacion($baja->fecha_envio)
            ->setCorrelativo(str_pad((string) $baja->correlativo, 3, '0', STR_PAD_LEFT))
            ->setCompany($this->empresaGreenter($baja))
            ->setDetails($this->detallesGreenter($baja));
    }

    protected function empresaGreenter(ComunicacionBaja $baja): Company
    {
        $configuracion = $baja->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($baja->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $baja->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $baja->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $baja->empresa->nombre)
            ->setAddress($address);
    }

    protected function detallesGreenter(ComunicacionBaja $baja): array
    {
        return $baja->detalles->values()->map(function ($detalle) {
            return (new VoidedDetail())
                ->setTipoDoc($detalle->tipo_documento)
                ->setSerie($detalle->serie)
                ->setCorrelativo((string) $detalle->correlativo)
                ->setDesMotivoBaja($detalle->motivo_baja);
        })->all();
    }
}
