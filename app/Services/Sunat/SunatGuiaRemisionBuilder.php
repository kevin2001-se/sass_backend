<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\GuiaRemision;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\AdditionalDoc;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;

class SunatGuiaRemisionBuilder
{
    public function buildFromGuia(GuiaRemision $guia): Despatch
    {
        $guia->loadMissing([
            'empresa.sunatConfiguraciones',
            'detalles.producto',
            'venta',
            'comprobante',
        ]);

        return (new Despatch())
            ->setVersion('2022')
            ->setTipoDoc('09')
            ->setSerie($guia->serie)
            ->setCorrelativo((string) $guia->correlativo)
            ->setFechaEmision($guia->fecha_emision)
            ->setCompany($this->empresaGreenter($guia))
            ->setDestinatario($this->destinatarioGreenter($guia))
            ->setObservacion($guia->observacion)
            ->setAddDocs($this->documentosRelacionadosGreenter($guia))
            ->setEnvio($this->envioGreenter($guia))
            ->setDetails($this->detallesGreenter($guia));
    }

    protected function empresaGreenter(GuiaRemision $guia): Company
    {
        $configuracion = $guia->empresa->sunatConfiguraciones->firstWhere('estado', true);

        $address = (new Address())
            ->setUbigueo($configuracion?->ubigeo ?: '000000')
            ->setDepartamento($configuracion?->departamento ?: '-')
            ->setProvincia($configuracion?->provincia ?: '-')
            ->setDistrito($configuracion?->distrito ?: '-')
            ->setDireccion($configuracion?->direccion_fiscal ?: ($guia->empresa->direccion ?: '-'));

        return (new Company())
            ->setRuc($configuracion?->ruc ?: $guia->empresa->ruc)
            ->setRazonSocial($configuracion?->razon_social ?: $guia->empresa->nombre)
            ->setNombreComercial($configuracion?->nombre_comercial ?: $guia->empresa->nombre)
            ->setAddress($address);
    }

    protected function destinatarioGreenter(GuiaRemision $guia): Client
    {
        return (new Client())
            ->setTipoDoc($this->tipoDocSunat($guia->destinatario_tipo_documento))
            ->setNumDoc($guia->destinatario_numero_documento ?: '00000000')
            ->setRznSocial($guia->destinatario_nombre ?: 'DESTINATARIO VARIOS');
    }

    protected function envioGreenter(GuiaRemision $guia): Shipment
    {
        $envio = (new Shipment())
            ->setModTraslado($guia->modalidad_transporte)
            ->setCodTraslado($guia->motivo_traslado_codigo)
            ->setDesTraslado($guia->motivo_traslado_descripcion)
            ->setFecTraslado($guia->fecha_traslado->toDateTime())
            ->setPesoTotal(round((float) $guia->peso_total, 3))
            ->setUndPesoTotal($guia->unidad_peso)
            ->setNumBultos($guia->numero_bultos)
            ->setLlegada(new Direction($guia->punto_llegada_ubigeo, $guia->punto_llegada_direccion))
            ->setPartida(new Direction($guia->punto_partida_ubigeo, $guia->punto_partida_direccion));

        if ($guia->modalidad_transporte === '01') {
            $envio->setTransportista((new Transportist())
                ->setTipoDoc('6')
                ->setNumDoc($guia->transportista_ruc ?: $guia->transportista_numero_documento)
                ->setRznSocial($guia->transportista_razon_social));
        }

        if ($guia->modalidad_transporte === '02') {
            [$nombres, $apellidos] = $this->splitNombre($guia->conductor_nombre);

            $envio->setVehiculo((new Vehicle())->setPlaca($guia->vehiculo_placa))
                ->setChoferes([
                    (new Driver())
                        ->setTipo('Principal')
                        ->setTipoDoc($this->tipoDocConductorSunat($guia->conductor_tipo_documento))
                        ->setNroDoc($guia->conductor_numero_documento)
                        ->setNombres($nombres)
                        ->setApellidos($apellidos)
                        ->setLicencia($this->licenciaConductorSunat($guia->conductor_licencia)),
                ]);
        }

        return $envio;
    }

    protected function detallesGreenter(GuiaRemision $guia): array
    {
        return $guia->detalles->values()->map(function ($detalle) {
            return (new DespatchDetail())
                ->setCantidad(round((float) $detalle->cantidad, 4))
                ->setUnidad($detalle->unidad_medida)
                ->setDescripcion($detalle->descripcion)
                ->setCodigo($detalle->codigo_producto ?: (string) ($detalle->producto?->codigo_interno ?: $detalle->producto_id));
        })->all();
    }

    protected function documentosRelacionadosGreenter(GuiaRemision $guia): array
    {
        if (! $guia->referencia_serie || ! $guia->referencia_numero) {
            return [];
        }

        return [
            (new AdditionalDoc())
                ->setTipoDesc($guia->tipo_referencia ?: '-')
                ->setTipo($this->tipoDocumentoRelacionadoSunat($guia->tipo_referencia ?: ''))
                ->setNro($guia->referencia_serie.'-'.$guia->referencia_numero)
                ->setEmisor($guia->empresa->ruc),
        ];
    }

    protected function tipoDocSunat(?string $tipo): string
    {
        return match ($tipo) {
            Cliente::RUC, 'RUC' => '6',
            Cliente::DNI, 'DNI' => '1',
            Cliente::CE, 'CE' => '4',
            'PASAPORTE' => '7',
            default => '0',
        };
    }

    protected function tipoDocConductorSunat(?string $tipo): string
    {
        return match ($tipo) {
            Cliente::DNI, 'DNI', '1', '01' => '1',
            Cliente::CE, 'CE', '4', '04' => '4',
            default => (string) $tipo,
        };
    }

    protected function licenciaConductorSunat(?string $licencia): string
    {
        $licencia = strtoupper(trim((string) $licencia));
        $licencia = preg_replace('/[^A-Z0-9]/', '', $licencia) ?: $licencia;

        return $licencia;
    }
    protected function tipoDocumentoRelacionadoSunat(string $tipo): string
    {
        return match ($tipo) {
            'FACTURA' => '01',
            'BOLETA' => '03',
            'NOTA_CREDITO' => '07',
            'NOTA_DEBITO' => '08',
            'GUIA_REMISION' => '09',
            'NOTA_VENTA' => '00',
            default => $tipo,
        };
    }

    protected function splitNombre(?string $nombre): array
    {
        $nombre = trim((string) $nombre);
        $partes = preg_split('/\s+/', $nombre, 2);

        return [$partes[0] ?? $nombre ?: '-', $partes[1] ?? '-'];
    }
}