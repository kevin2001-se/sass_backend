<?php

namespace App\Services\Sunat;

use App\Models\ComunicacionBaja;
use App\Models\ComunicacionBajaDetalle;
use App\Models\Venta;
use Carbon\Carbon;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;

class SunatComunicacionBajaBuilder
{
    public function buildFromComunicacion(ComunicacionBaja $comunicacion): Voided
    {
        $comunicacion->loadMissing(['empresa.sunatConfiguraciones', 'detalles']);

        return (new Voided())
            ->setCorrelativo(str_pad((string) $comunicacion->correlativo, 3, '0', STR_PAD_LEFT))
            ->setFecGeneracion($this->fechaPeru($comunicacion->fecha_baja))
            ->setFecComunicacion($this->fechaPeru($comunicacion->fecha_envio ?: $comunicacion->fecha_baja))
            ->setCompany($this->empresaGreenter($comunicacion))
            ->setDetails($this->detallesGreenter($comunicacion));
    }

    protected function fechaPeru(mixed $fecha): Carbon
    {
        return Carbon::parse($fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : $fecha, 'America/Lima')->startOfDay();
    }
    protected function empresaGreenter(ComunicacionBaja $comunicacion): Company
    {
        $configuracion = $comunicacion->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($comunicacion->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $comunicacion->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $comunicacion->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $comunicacion->empresa->nombre)
            ->setAddress($address);
    }

    protected function detallesGreenter(ComunicacionBaja $comunicacion): array
    {
        return $comunicacion->detalles->values()->map(function (ComunicacionBajaDetalle $detalle) {
            return (new VoidedDetail())
                ->setTipoDoc($this->tipoDocumentoSunat($detalle->tipo_documento))
                ->setSerie($detalle->serie)
                ->setCorrelativo((string) $detalle->correlativo)
                ->setDesMotivoBaja($detalle->motivo_baja ?: 'Baja de comprobante');
        })->all();
    }

    protected function tipoDocumentoSunat(string $tipo): string
    {
        return match ($tipo) {
            Venta::BOLETA, 'BOLETA', '03' => '03',
            Venta::FACTURA, 'FACTURA', '01' => '01',
            'NOTA_CREDITO', '07' => '07',
            'NOTA_DEBITO', '08' => '08',
            default => $tipo,
        };
    }
}
