<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\ComprobanteElectronico;
use App\Models\SunatConfiguracion;
use App\Models\Venta;
use Greenter\Model\Response\BaseResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ComprobanteElectronicoService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatInvoiceBuilder $invoiceBuilder
    ) {
    }

    public function emitirDesdeVenta(int $ventaId, ?array $scope = null): ComprobanteElectronico
    {
        $comprobante = DB::transaction(function () use ($ventaId, $scope) {
            $venta = $this->ventaQuery($scope)->lockForUpdate()->findOrFail($ventaId);
            $this->validarVentaParaEmision($venta);

            if ($this->comprobanteQuery($scope)->where('venta_id', $venta->id)->exists()) {
                throw ValidationException::withMessages([
                    'venta_id' => ['La venta ya tiene un comprobante electrÃ³nico registrado.'],
                ]);
            }

            $this->configuracionActiva($venta);

            return ComprobanteElectronico::create([
                'tenant_id' => $venta->tenant_id,
                'empresa_id' => $venta->empresa_id,
                'tienda_id' => $venta->tienda_id,
                'venta_id' => $venta->id,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'serie' => $venta->serie,
                'correlativo' => $venta->correlativo,
                'numero_comprobante' => $venta->numero_comprobante,
                'fecha_emision' => $venta->fecha_emision,
                'moneda' => 'PEN',
                'estado_sunat' => ComprobanteElectronico::PENDIENTE,
            ]);
        });

        return $this->enviarSunat($comprobante->refresh());
    }

    public function reenviar(int $comprobanteId, ?array $scope = null): ComprobanteElectronico
    {
        $comprobante = DB::transaction(function () use ($comprobanteId, $scope) {
            $comprobante = $this->comprobanteQuery($scope)
                ->with('venta')
                ->lockForUpdate()
                ->findOrFail($comprobanteId);

            if ($comprobante->estado_sunat === ComprobanteElectronico::ACEPTADO) {
                throw ValidationException::withMessages([
                    'comprobante' => ['La venta ya tiene un comprobante aceptado.'],
                ]);
            }

            return $comprobante;
        });

        return $this->enviarSunat($comprobante);
    }

    public function generarXml(ComprobanteElectronico $comprobante): string
    {
        $comprobante->loadMissing('venta.cliente', 'venta.empresa.sunatConfiguraciones', 'venta.tienda', 'venta.detalles.producto', 'venta.detalles.presentacion.unidadMedida');
        $this->validarVentaParaEmision($comprobante->venta);
        $configuracion = $this->configuracionActiva($comprobante->venta);
        $see = $this->clientFactory->make($configuracion);
        $invoice = $this->invoiceBuilder->buildFromVenta($comprobante->venta);
        $xml = $see->getXmlSigned($invoice);

        if (! $xml) {
            throw new RuntimeException('Greenter no pudo generar el XML firmado.');
        }

        $this->guardarXmlFirmado($comprobante, $xml);

        return $xml;
    }

    public function enviarSunat(ComprobanteElectronico $comprobante): ComprobanteElectronico
    {
        try {
            $comprobante->loadMissing('venta.cliente', 'venta.empresa.sunatConfiguraciones', 'venta.tienda', 'venta.detalles.producto', 'venta.detalles.presentacion.unidadMedida');
            $this->validarVentaParaEmision($comprobante->venta);

            $configuracion = $this->configuracionActiva($comprobante->venta);
            $see = $this->clientFactory->make($configuracion);
            $invoice = $this->invoiceBuilder->buildFromVenta($comprobante->venta);
            $xml = $see->getXmlSigned($invoice);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado.');
            }

            $this->guardarXmlFirmado($comprobante, $xml);
            $response = $see->sendXml($invoice::class, $invoice->getName(), $xml);

            if (! $response) {
                throw new RuntimeException('SUNAT no devolviÃ³ respuesta para el comprobante.');
            }

            return $this->actualizarEstadoSunat($comprobante->refresh(), $response);
        } catch (Throwable $e) {
            $comprobante->increment('intentos_envio');
            $comprobante->update([
                'estado_sunat' => ComprobanteElectronico::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
                'observacion' => trim(($comprobante->observacion ? $comprobante->observacion.' | ' : '').'ERROR: '.$e->getMessage()),
            ]);

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo enviar el comprobante a SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    public function guardarCdr(ComprobanteElectronico $comprobante, mixed $cdr): ?string
    {
        if (! $cdr) {
            return null;
        }

        Storage::disk('local')->put($this->cdrPath($comprobante), is_string($cdr) ? $cdr : (string) $cdr);
        $comprobante->update(['cdr_path' => $this->cdrPath($comprobante)]);

        return $this->cdrPath($comprobante);
    }

    public function generarQrText(ComprobanteElectronico $comprobante): string
    {
        $venta = $comprobante->venta;
        $cliente = $venta->cliente;
        $tipoDocumento = $comprobante->tipo_comprobante === Venta::FACTURA ? '01' : '03';

        return implode('|', [
            $venta->empresa->ruc,
            $tipoDocumento,
            $comprobante->serie,
            str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT),
            number_format((float) $venta->total_igv, 2, '.', ''),
            number_format((float) $venta->total, 2, '.', ''),
            $comprobante->fecha_emision->toDateString(),
            $cliente ? $this->tipoDocQr($cliente) : '0',
            $cliente?->numero_documento ?: '00000000',
        ]);
    }

    public function actualizarEstadoSunat(ComprobanteElectronico $comprobante, BaseResult $response): ComprobanteElectronico
    {
        $cdrResponse = method_exists($response, 'getCdrResponse') ? $response->getCdrResponse() : null;
        $error = $response->getError();
        $codigo = $cdrResponse?->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Respuesta SUNAT recibida.';
        $cdr = method_exists($response, 'getCdrZip') ? $response->getCdrZip() : null;
        $aceptado = ($cdrResponse && $cdrResponse->isAccepted()) || $response->isSuccess();

        if ($cdr) {
            $this->guardarCdr($comprobante, $cdr);
        }

        $comprobante->increment('intentos_envio');
        $comprobante->update([
            'estado_sunat' => $aceptado ? ComprobanteElectronico::ACEPTADO : ComprobanteElectronico::RECHAZADO,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'enviado_at' => now(),
            'aceptado_at' => $aceptado ? now() : null,
            'rechazado_at' => $aceptado ? null : now(),
        ]);

        return $comprobante->refresh();
    }

    protected function validarVentaParaEmision(Venta $venta): void
    {
        if ($venta->estado !== Venta::REGISTRADA) {
            throw ValidationException::withMessages(['venta' => ['No se puede emitir una venta anulada.']]);
        }

        if (! in_array($venta->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['tipo_comprobante' => ['Solo se emiten BOLETA y FACTURA.']]);
        }

        if ($venta->tipo_comprobante === Venta::FACTURA && (! $venta->cliente || $venta->cliente->tipo_documento !== Cliente::RUC)) {
            throw ValidationException::withMessages(['cliente_id' => ['Para FACTURA el cliente es obligatorio y debe tener RUC.']]);
        }
    }

    protected function configuracionActiva(Venta $venta): SunatConfiguracion
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $venta->tenant_id)
            ->where('empresa_id', $venta->empresa_id)
            ->where('estado', true)
            ->first();

        if (! $configuracion) {
            throw ValidationException::withMessages(['sunat_configuracion' => ['No existe configuraciÃ³n SUNAT activa para esta empresa.']]);
        }

        return $configuracion;
    }

    protected function ventaQuery(?array $scope)
    {
        $query = Venta::with(['cliente', 'empresa.sunatConfiguraciones', 'tienda', 'detalles.producto', 'detalles.presentacion.unidadMedida']);

        return $scope
            ? $query->where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])
            : $query;
    }

    protected function comprobanteQuery(?array $scope)
    {
        $query = ComprobanteElectronico::query();

        return $scope
            ? $query->where('tenant_id', $scope['tenant_id'])->where('empresa_id', $scope['empresa_id'])->where('tienda_id', $scope['tienda_id'])
            : $query;
    }

    protected function guardarXmlFirmado(ComprobanteElectronico $comprobante, string $xml): void
    {
        Storage::disk('local')->put($this->xmlPath($comprobante), $xml);
        $comprobante->update([
            'xml_path' => $this->xmlPath($comprobante),
            'hash' => hash('sha256', $xml),
            'qr_text' => $this->generarQrText($comprobante),
        ]);
    }

    protected function xmlPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/comprobantes/'.$comprobante->empresa_id.'/'.$comprobante->tipo_comprobante.'/'.$comprobante->fecha_emision->format('Y-m-d').'/xml/'.$comprobante->numero_comprobante.'.xml';
    }

    protected function cdrPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/comprobantes/'.$comprobante->empresa_id.'/'.$comprobante->tipo_comprobante.'/'.$comprobante->fecha_emision->format('Y-m-d').'/cdr/R-'.$comprobante->numero_comprobante.'.zip';
    }

    protected function tipoDocQr(Cliente $cliente): string
    {
        return match ($cliente->tipo_documento) {
            Cliente::RUC => '6',
            Cliente::DNI => '1',
            Cliente::CE => '4',
            default => '0',
        };
    }
}
