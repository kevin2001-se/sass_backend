<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\NotaDebito;
use App\Models\Venta;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SunatNotaDebitoBuilder
{
    private const IGV = 0.18;

    public function buildFromNotaDebito(NotaDebito $nota): Note
    {
        $nota->loadMissing([
            'empresa.sunatConfiguraciones',
            'venta.cliente',
            'comprobante',
            'motivo',
            'detalles',
        ]);

        $this->validarNota($nota);

        $tipoDocAfectado = $nota->comprobante->tipo_comprobante === Venta::FACTURA ? '01' : '03';

        return (new Note())
            ->setUblVersion('2.1')
            ->setTipoDoc('08')
            ->setSerie($nota->serie)
            ->setCorrelativo((string) $nota->correlativo)
            ->setFechaEmision($nota->created_at)
            ->setTipDocAfectado($tipoDocAfectado)
            ->setNumDocfectado($nota->comprobante->numero_comprobante)
            ->setCodMotivo($nota->motivo_codigo)
            ->setDesMotivo($nota->motivo_descripcion ?: $nota->motivo?->descripcion ?: 'Nota de debito')
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

    protected function empresaGreenter(NotaDebito $nota): Company
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

    protected function clienteGreenter(NotaDebito $nota): Client
    {
        $cliente = $nota->venta->cliente;

        if (! $cliente) {
            return (new Client())->setTipoDoc('0')->setNumDoc('00000000')->setRznSocial('CLIENTES VARIOS');
        }

        return (new Client())
            ->setTipoDoc(match ($cliente->tipo_documento) {
                Cliente::RUC => '6',
                Cliente::DNI => '1',
                Cliente::CE => '4',
                default => '0',
            })
            ->setNumDoc($cliente->numero_documento ?: '00000000')
            ->setRznSocial($cliente->razon_social ?: $cliente->nombres);
    }

    protected function detallesGreenter(NotaDebito $nota): array
    {
        return $nota->detalles->values()->map(function ($detalle, int $index) use ($nota) {
            $precioUnitario = round((float) $detalle->precio_unitario, 2);

            $item = (new SaleDetail())
                ->setUnidad('NIU')
                ->setCantidad(round((float) $detalle->cantidad, 4))
                ->setCodProducto((string) ($index + 1))
                ->setDescripcion($detalle->descripcion)
                ->setMtoBaseIgv(round((float) $detalle->subtotal, 2))
                ->setPorcentajeIgv(18)
                ->setIgv(round((float) $detalle->igv, 2))
                ->setTipAfeIgv('10')
                ->setTotalImpuestos(round((float) $detalle->igv, 2))
                ->setMtoValorVenta(round((float) $detalle->subtotal, 2))
                ->setMtoValorUnitario(round($precioUnitario / (1 + self::IGV), 6))
                ->setMtoPrecioUnitario($precioUnitario);

            Log::debug('SUNAT detalle nota debito', [
                'nota_debito_id' => $nota->id,
                'detalle_id' => $detalle->id,
                'unidad_sunat' => 'NIU',
            ]);

            return $item;
        })->all();
    }

    protected function leyendasGreenter(NotaDebito $nota): array
    {
        return [
            (new Legend())
                ->setCode('1000')
                ->setValue('SON '.number_format((float) $nota->total, 2, '.', '').' SOLES'),
        ];
    }

    protected function validarNota(NotaDebito $nota): void
    {
        if (! $nota->comprobante || ! in_array($nota->comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_id' => ['La nota de debito debe referenciar BOLETA o FACTURA.']]);
        }

        if (! $nota->venta?->cliente && $nota->comprobante->tipo_comprobante === Venta::FACTURA) {
            throw ValidationException::withMessages(['cliente' => ['La factura referenciada debe tener cliente valido.']]);
        }

        if ($nota->detalles->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => ['La nota de debito no tiene detalles.']]);
        }

        $sumTotal = round((float) $nota->detalles->sum('total'), 2);
        $sumIgv = round((float) $nota->detalles->sum('igv'), 2);

        if (abs($sumTotal - round((float) $nota->total, 2)) > 0.02) {
            throw ValidationException::withMessages(['total' => ['El total de detalles no cuadra con la nota de debito.']]);
        }

        if (abs($sumIgv - round((float) $nota->total_igv, 2)) > 0.02) {
            throw ValidationException::withMessages(['total_igv' => ['El IGV de detalles no cuadra con la nota de debito.']]);
        }
    }
}