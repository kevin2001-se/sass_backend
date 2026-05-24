<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\NotaCredito;
use App\Models\Venta;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SunatNotaCreditoBuilder
{
    private const IGV = 0.18;

    public function buildFromNotaCredito(NotaCredito $nota): Note
    {
        $nota->loadMissing([
            'empresa.sunatConfiguraciones',
            'venta.cliente',
            'comprobante',
            'detalles.ventaDetalle.presentacion.unidadMedida',
        ]);

        $this->validarNota($nota);

        $gravadas = round((float) $nota->detalles->filter(fn ($detalle) => (bool) $detalle->ventaDetalle?->afecto_igv)->sum('subtotal'), 2);
        $inafectas = round((float) $nota->detalles->filter(fn ($detalle) => ! (bool) $detalle->ventaDetalle?->afecto_igv)->sum('subtotal'), 2);
        $descuentos = round((float) $nota->detalles->sum('descuento'), 2);
        $tipoDocAfectado = $nota->comprobante->tipo_comprobante === Venta::FACTURA ? '01' : '03';

        $note = (new Note())
            ->setUblVersion('2.1')
            ->setTipoDoc('07')
            ->setSerie($nota->serie)
            ->setCorrelativo((string) $nota->correlativo)
            ->setFechaEmision($nota->created_at)
            ->setTipDocAfectado($tipoDocAfectado)
            ->setNumDocfectado($nota->comprobante->numero_comprobante)
            ->setCodMotivo($nota->motivo_codigo)
            ->setDesMotivo($nota->motivo_descripcion)
            ->setTipoMoneda('PEN')
            ->setCompany($this->empresaGreenter($nota))
            ->setClient($this->clienteGreenter($nota))
            ->setMtoOperGravadas($gravadas > 0 ? $gravadas : null)
            ->setMtoOperInafectas($inafectas > 0 ? $inafectas : null)
            ->setMtoIGV(round((float) $nota->total_igv, 2))
            ->setTotalImpuestos(round((float) $nota->total_igv, 2))
            ->setValorVenta(round((float) $nota->subtotal, 2))
            ->setSubTotal(round((float) $nota->total, 2))
            ->setMtoImpVenta(round((float) $nota->total, 2))
            ->setDetails($this->detallesGreenter($nota))
            ->setLegends($this->leyendasGreenter($nota));

        if ($descuentos > 0 && method_exists($note, 'setMtoDescuentos')) {
            $note->setMtoDescuentos($descuentos);
        }

        return $note;
    }

    protected function empresaGreenter(NotaCredito $nota): Company
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

    protected function clienteGreenter(NotaCredito $nota): Client
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

    protected function detallesGreenter(NotaCredito $nota): array
    {
        return $nota->detalles->values()->map(function ($detalle, int $index) use ($nota) {
            $ventaDetalle = $detalle->ventaDetalle;
            $afectoIgv = (bool) $ventaDetalle?->afecto_igv;
            $precioUnitario = round((float) $detalle->precio_unitario, 2);
            $descuento = round((float) $detalle->descuento, 2);

            $item = (new SaleDetail())
                ->setUnidad($this->unidadSunat($detalle))
                ->setCantidad(round((float) $detalle->cantidad, 4))
                ->setCodProducto((string) ($detalle->producto_id ?: ($index + 1)))
                ->setDescripcion($detalle->descripcion)
                ->setMtoBaseIgv(round((float) $detalle->subtotal, 2))
                ->setPorcentajeIgv($afectoIgv ? 18 : 0)
                ->setIgv(round((float) $detalle->igv, 2))
                ->setTipAfeIgv($afectoIgv ? '10' : '30')
                ->setTotalImpuestos(round((float) $detalle->igv, 2))
                ->setMtoValorVenta(round((float) $detalle->subtotal, 2))
                ->setMtoValorUnitario($afectoIgv ? round($precioUnitario / (1 + self::IGV), 6) : round($precioUnitario, 6))
                ->setMtoPrecioUnitario($precioUnitario);

            if ($descuento > 0 && method_exists($item, 'setDescuento')) {
                $item->setDescuento($descuento);
            }

            Log::debug('SUNAT detalle nota credito', [
                'nota_credito_id' => $nota->id,
                'detalle_id' => $detalle->id,
                'venta_detalle_id' => $detalle->venta_detalle_id,
                'unidad_sunat' => $this->unidadSunat($detalle),
                'descuento' => $descuento,
            ]);

            return $item;
        })->all();
    }

    protected function leyendasGreenter(NotaCredito $nota): array
    {
        return [
            (new Legend())
                ->setCode('1000')
                ->setValue('SON '.number_format((float) $nota->total, 2, '.', '').' SOLES'),
        ];
    }

    protected function unidadSunat($detalle): string
    {
        $codigo = strtoupper(trim((string) ($detalle->unidad_medida ?: $detalle->ventaDetalle?->presentacion?->unidadMedida?->codigo_sunat ?: '')));

        if ($codigo === '') {
            Log::warning('SUNAT nota credito detalle sin unidad SUNAT, usando NIU.', [
                'nota_credito_detalle_id' => $detalle->id,
                'venta_detalle_id' => $detalle->venta_detalle_id,
            ]);

            return 'NIU';
        }

        return $codigo;
    }

    protected function validarNota(NotaCredito $nota): void
    {
        if (! $nota->comprobante || ! in_array($nota->comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_id' => ['La nota de credito debe referenciar BOLETA o FACTURA.']]);
        }

        if (! $nota->venta?->cliente && $nota->comprobante->tipo_comprobante === Venta::FACTURA) {
            throw ValidationException::withMessages(['cliente' => ['La factura referenciada debe tener cliente valido.']]);
        }

        if ($nota->detalles->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => ['La nota de credito no tiene detalles.']]);
        }

        $sumTotal = round((float) $nota->detalles->sum('total'), 2);
        $sumIgv = round((float) $nota->detalles->sum('igv'), 2);

        if (abs($sumTotal - round((float) $nota->total, 2)) > 0.02) {
            throw ValidationException::withMessages(['total' => ['El total de detalles no cuadra con la nota de credito.']]);
        }

        if (abs($sumIgv - round((float) $nota->total_igv, 2)) > 0.02) {
            throw ValidationException::withMessages(['total_igv' => ['El IGV de detalles no cuadra con la nota de credito.']]);
        }
    }
}
