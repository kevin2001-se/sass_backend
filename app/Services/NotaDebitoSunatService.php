<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\NotaDebito;
use App\Models\SunatConfiguracion;
use App\Models\Venta;
use App\Services\Sunat\SunatClientFactory;
use App\Services\Sunat\SunatNotaDebitoBuilder;
use Greenter\Model\Response\BaseResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class NotaDebitoSunatService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatNotaDebitoBuilder $builder
    ) {
    }

    public function enviar(int $notaId, array $scope): NotaDebito
    {
        $nota = DB::transaction(function () use ($notaId, $scope) {
            $nota = $this->findScoped($notaId, $scope, true);
            $this->validarParaEnvio($nota, false);
            $nota->update([
                'estado_sunat' => NotaDebito::SUNAT_ENVIADO,
                'enviado_at' => now(),
            ]);

            return $nota->refresh();
        });

        return $this->enviarConGreenter($nota);
    }

    public function reenviar(int $notaId, array $scope): NotaDebito
    {
        $nota = DB::transaction(function () use ($notaId, $scope) {
            $nota = $this->findScoped($notaId, $scope, true);
            $this->validarParaEnvio($nota, true);
            $nota->update([
                'estado_sunat' => NotaDebito::SUNAT_ENVIADO,
                'enviado_at' => now(),
            ]);

            return $nota->refresh();
        });

        return $this->enviarConGreenter($nota);
    }

    public function guardarXml(NotaDebito $nota, string $xml): string
    {
        $path = $this->xmlPath($nota);
        Storage::disk('local')->put($path, $xml);
        $nota->update([
            'xml_path' => $path,
            'hash' => hash('sha256', $xml),
            'qr_text' => $this->generarQrText($nota),
        ]);

        return $path;
    }

    public function guardarCdr(NotaDebito $nota, mixed $cdr): ?string
    {
        if (! $cdr) {
            return null;
        }

        $path = $this->cdrPath($nota);
        Storage::disk('local')->put($path, is_string($cdr) ? $cdr : (string) $cdr);
        $nota->update(['cdr_path' => $path]);

        return $path;
    }

    public function actualizarEstado(NotaDebito $nota, BaseResult $response): NotaDebito
    {
        $cdrResponse = method_exists($response, 'getCdrResponse') ? $response->getCdrResponse() : null;
        $error = method_exists($response, 'getError') ? $response->getError() : null;
        $codigo = $cdrResponse?->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Respuesta SUNAT recibida.';
        $cdr = method_exists($response, 'getCdrZip') ? $response->getCdrZip() : null;
        $aceptado = ($cdrResponse && $cdrResponse->isAccepted()) || (method_exists($response, 'isSuccess') && $response->isSuccess() && ! $error);

        if ($cdr) {
            $this->guardarCdr($nota, $cdr);
        }

        $nota->increment('intentos_envio');
        $nota->update([
            'estado_sunat' => $aceptado ? NotaDebito::SUNAT_ACEPTADO : NotaDebito::SUNAT_RECHAZADO,
            'codigo_respuesta' => $this->codigoSeguro($codigo, $aceptado),
            'mensaje_respuesta' => $this->mensajeSeguro($mensaje),
            'enviado_at' => now(),
            'aceptado_at' => $aceptado ? now() : null,
            'rechazado_at' => $aceptado ? null : now(),
        ]);

        $this->logSunat($nota->refresh(), 'respuesta_sunat');

        return $this->cargar($nota);
    }

    public function generarQrText(NotaDebito $nota): string
    {
        $nota->loadMissing(['empresa', 'venta.cliente']);
        $cliente = $nota->venta->cliente;

        return implode('|', [
            $nota->empresa?->ruc,
            '08',
            $nota->serie,
            str_pad((string) $nota->correlativo, 8, '0', STR_PAD_LEFT),
            number_format((float) $nota->total_igv, 2, '.', ''),
            number_format((float) $nota->total, 2, '.', ''),
            $nota->created_at?->toDateString(),
            $cliente ? $this->tipoDocQr($cliente->tipo_documento) : '0',
            $cliente?->numero_documento ?: '00000000',
        ]);
    }

    protected function enviarConGreenter(NotaDebito $nota): NotaDebito
    {
        try {
            $nota = $this->cargar($nota);
            $configuracion = $this->configuracionActiva($nota);
            $see = $this->clientFactory->make($configuracion);
            $debitNote = $this->builder->buildFromNotaDebito($nota);
            $xml = $see->getXmlSigned($debitNote);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado de la nota de debito.');
            }

            $this->guardarXml($nota, $xml);
            $response = $see->sendXml($debitNote::class, $debitNote->getName(), $xml);

            if (! $response) {
                throw new RuntimeException('SUNAT no devolvio respuesta para la nota de debito.');
            }

            return $this->actualizarEstado($nota->refresh(), $response);
        } catch (Throwable $e) {
            $nota->increment('intentos_envio');
            $nota->update([
                'estado_sunat' => NotaDebito::SUNAT_ERROR,
                'codigo_respuesta' => 'ERROR',
                'mensaje_respuesta' => $this->mensajeSeguro($e->getMessage()),
                'enviado_at' => now(),
            ]);

            $this->logSunat($nota->refresh(), 'error_sunat');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo enviar la nota de debito a SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    protected function validarParaEnvio(NotaDebito $nota, bool $reenvio): void
    {
        $nota = $this->cargar($nota);

        if ($nota->estado === NotaDebito::ANULADA) {
            throw ValidationException::withMessages(['nota_debito' => ['No se puede enviar una nota de debito anulada.']]);
        }

        if ($nota->estado !== NotaDebito::REGISTRADA) {
            throw ValidationException::withMessages(['nota_debito' => ['Solo se puede enviar una nota de debito REGISTRADA.']]);
        }

        if ($nota->estado_sunat === NotaDebito::SUNAT_ACEPTADO) {
            throw ValidationException::withMessages(['nota_debito' => ['La nota de debito ya fue aceptada por SUNAT.']]);
        }

        if (! $reenvio && ! in_array($nota->estado_sunat ?: NotaDebito::SUNAT_PENDIENTE, [NotaDebito::SUNAT_PENDIENTE, NotaDebito::SUNAT_ERROR, NotaDebito::SUNAT_RECHAZADO], true)) {
            throw ValidationException::withMessages(['nota_debito' => ['La nota de debito no esta disponible para envio SUNAT.']]);
        }

        if (! $nota->comprobante || ! in_array($nota->comprobante->tipo_comprobante, [Venta::BOLETA, Venta::FACTURA], true)) {
            throw ValidationException::withMessages(['comprobante_id' => ['El comprobante original es invalido.']]);
        }

        if ($nota->comprobante->estado_sunat !== ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['comprobante_id' => ['El comprobante original debe estar ACEPTADO.']]);
        }

        if ($nota->detalles->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => ['La nota de debito no tiene detalles.']]);
        }

        $this->configuracionActiva($nota);
    }

    protected function configuracionActiva(NotaDebito $nota): SunatConfiguracion
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $nota->tenant_id)
            ->where('empresa_id', $nota->empresa_id)
            ->where('estado', true)
            ->first();

        if (! $configuracion) {
            throw ValidationException::withMessages(['sunat_configuracion' => ['No existe configuracion SUNAT activa para esta empresa.']]);
        }

        if (! $configuracion->certificado_path) {
            throw ValidationException::withMessages(['sunat_configuracion' => ['La configuracion SUNAT no tiene certificado digital.']]);
        }

        return $configuracion;
    }

    protected function findScoped(int $notaId, array $scope, bool $lock = false): NotaDebito
    {
        $query = NotaDebito::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($notaId);
    }

    protected function cargar(NotaDebito $nota): NotaDebito
    {
        return $nota->loadMissing([
            'empresa.sunatConfiguraciones',
            'venta.cliente',
            'comprobante',
            'motivo',
            'detalles',
        ]);
    }

    protected function xmlPath(NotaDebito $nota): string
    {
        return 'private/sunat/notas-debito/'.$nota->empresa_id.'/'.$nota->created_at->format('Y-m-d').'/xml/'.$nota->numero_completo.'.xml';
    }

    protected function cdrPath(NotaDebito $nota): string
    {
        return 'private/sunat/notas-debito/'.$nota->empresa_id.'/'.$nota->created_at->format('Y-m-d').'/cdr/R-'.$nota->numero_completo.'.zip';
    }

    protected function codigoSeguro(?string $codigo, bool $aceptado = false): string
    {
        $codigo = trim((string) $codigo);

        return $codigo !== '' ? Str::limit($codigo, 20, '') : ($aceptado ? '0' : 'ERROR');
    }

    protected function mensajeSeguro(?string $mensaje): string
    {
        $mensaje = trim((string) $mensaje);

        return $mensaje !== '' ? Str::limit($mensaje, 2000, '') : 'Respuesta SUNAT recibida.';
    }

    protected function tipoDocQr(?string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'RUC' => '6',
            'DNI' => '1',
            'CE' => '4',
            default => '0',
        };
    }

    protected function logSunat(NotaDebito $nota, string $evento): void
    {
        Log::info('SUNAT nota debito', [
            'evento' => $evento,
            'tenant_id' => $nota->tenant_id,
            'empresa_id' => $nota->empresa_id,
            'nota_debito_id' => $nota->id,
            'numero' => $nota->numero_completo,
            'estado_sunat' => $nota->estado_sunat,
            'codigo_respuesta' => $nota->codigo_respuesta,
            'mensaje_respuesta' => $nota->mensaje_respuesta,
        ]);
    }
}