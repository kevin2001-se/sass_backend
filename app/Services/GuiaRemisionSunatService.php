<?php

namespace App\Services;

use App\Models\GuiaRemision;
use App\Models\SunatConfiguracion;
use App\Services\Sunat\GreSunatClientFactory;
use App\Services\Sunat\SunatGuiaRemisionBuilder;
use Greenter\Sunat\GRE\Model\CpeDocument;
use Greenter\Sunat\GRE\Model\CpeDocumentArchivo;
use Greenter\Sunat\GRE\Model\StatusResponse;
use Greenter\Model\Response\StatusResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class GuiaRemisionSunatService
{
    public function __construct(
        private readonly GreSunatClientFactory $clientFactory,
        private readonly SunatGuiaRemisionBuilder $builder,
    ) {
    }

    public function enviar(int $guiaId, array $scope): GuiaRemision
    {
        $guia = DB::transaction(function () use ($guiaId, $scope) {
            $guia = $this->findScoped($guiaId, $scope, true);
            $this->validarParaEnvio($guia, false);
            $guia->update([
                'estado_sunat' => GuiaRemision::SUNAT_ENVIADO,
                'enviado_at' => now(),
            ]);

            return $guia->refresh();
        });

        return $this->enviarConGreenter($guia);
    }

    public function reenviar(int $guiaId, array $scope): GuiaRemision
    {
        $guia = DB::transaction(function () use ($guiaId, $scope) {
            $guia = $this->findScoped($guiaId, $scope, true);
            $this->validarParaEnvio($guia, true);
            $guia->update([
                'estado_sunat' => GuiaRemision::SUNAT_ENVIADO,
                'enviado_at' => now(),
            ]);

            return $guia->refresh();
        });

        return $this->enviarConGreenter($guia);
    }

    public function guardarXml(GuiaRemision $guia, string $xml): string
    {
        $path = $this->xmlPath($guia);
        Storage::disk('local')->put($path, $xml);
        $guia->update([
            'xml_path' => $path,
            'hash' => hash('sha256', $xml),
            'qr_text' => $this->generarQrText($guia),
        ]);

        return $path;
    }

    public function guardarCdr(GuiaRemision $guia, mixed $cdr): ?string
    {
        if (! $cdr) {
            return null;
        }

        $path = $this->cdrPath($guia);
        Storage::disk('local')->put($path, is_string($cdr) ? $cdr : (string) $cdr);
        $guia->update(['cdr_path' => $path]);

        return $path;
    }

    public function actualizarEstadoDesdeGre(StatusResponse $status, GuiaRemision $guia, ?string $ticket = null): GuiaRemision
    {
        $codigo = $status->getCodRespuesta();
        $error = $status->getError();
        $mensaje = $error?->getDesError() ?: ($codigo === '0' ? 'La guia de remision ha sido aceptada.' : 'Respuesta GRE recibida.');
        $cdr = $status->getArcCdr();
        $aceptado = $codigo === '0';

        if ($cdr) {
            $decoded = base64_decode($cdr, true);
            $this->guardarCdr($guia, $decoded !== false ? $decoded : $cdr);
        }

        $guia->increment('intentos_envio');
        $guia->update([
            'estado_sunat' => $aceptado ? GuiaRemision::SUNAT_ACEPTADO : GuiaRemision::SUNAT_RECHAZADO,
            'codigo_respuesta' => $this->codigoRespuestaSeguro($codigo, $aceptado, false),
            'mensaje_respuesta' => $this->mensajeRespuestaSeguro($mensaje),
            'enviado_at' => now(),
            'aceptado_at' => $aceptado ? now() : null,
            'rechazado_at' => $aceptado ? null : now(),
        ]);

        $this->logGre($guia->refresh(), 'respuesta_gre');

        return $this->cargar($guia);
    }

    public function generarQrText(GuiaRemision $guia): string
    {
        return implode('|', [
            $guia->empresa?->ruc,
            '09',
            $guia->serie,
            str_pad((string) $guia->correlativo, 8, '0', STR_PAD_LEFT),
            $guia->fecha_emision?->toDateString(),
            $guia->destinatario_tipo_documento,
            $guia->destinatario_numero_documento,
        ]);
    }

    protected function enviarConGreenter(GuiaRemision $guia): GuiaRemision
    {
        try {
            $guia = $this->cargar($guia);
            $configuracion = $this->configuracionActiva($guia);
            $this->clientFactory->validarConfiguracion($configuracion);

            $this->clientFactory->probarAutorizacion($configuracion);
            $api = $this->clientFactory->makeApi($configuracion);
            $despatch = $this->builder->buildFromGuia($guia);
            $result = $api->send($despatch);
            $xml = $api->getLastXml();

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado de la guia.');
            }

            $this->guardarXml($guia, $xml);

            if (! $result || ! $result->isSuccess()) {
                $error = $result?->getError();
                $message = $error?->getMessage() ?: 'SUNAT GRE no acepto el envio de la guia.';
                if (str_contains(strtolower($message), 'cliente no autorizado') || str_contains(strtolower($message), 'unauthorized')) {
                    $message = 'Cliente No autorizado. Revise GRE Client ID, GRE Client Secret, usuario SOL GRE, clave SOL GRE, ambiente y endpoints GRE. En BETA use credenciales beta; en PRODUCCION use credenciales API SUNAT del RUC emisor.';
                }
                throw new RuntimeException($message);
            }

            $ticket = method_exists($result, 'getTicket') ? $result->getTicket() : null;
            $this->logGre($guia, 'enviado_gre', $ticket);

            if (! $ticket) {
                throw new RuntimeException('SUNAT GRE no devolvio ticket de envio.');
            }

            $status = $api->getStatus($ticket);

            return $this->actualizarEstadoDesdeGreenterApi($status, $guia->refresh(), $ticket);
        } catch (Throwable $e) {
            $guia->increment('intentos_envio');
            $guia->update([
                'estado_sunat' => GuiaRemision::SUNAT_ERROR,
                'codigo_respuesta' => 'ERROR',
                'mensaje_respuesta' => $this->mensajeRespuestaSeguro($e->getMessage()),
                'enviado_at' => now(),
            ]);

            $this->logGre($guia->refresh(), 'error_gre');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo enviar la guia a SUNAT GRE. '.$e->getMessage()],
            ]);
        }
    }

    protected function actualizarEstadoDesdeGreenterApi(StatusResult $status, GuiaRemision $guia, ?string $ticket = null): GuiaRemision
    {
        $codigoEstado = $status->getCode();
        $error = $status->getError();
        $cdrResponse = $status->getCdrResponse();
        $cdrZip = $status->getCdrZip();

        if ($cdrZip) {
            $this->guardarCdr($guia, $cdrZip);
        }

        $mensaje = $cdrResponse?->getDescription() ?: $error?->getMessage() ?: ($status->isSuccess() ? 'La guia de remision ha sido aceptada.' : 'Respuesta GRE recibida.');
        $pendiente = $codigoEstado === '98';
        $aceptado = (bool) ($cdrResponse?->isAccepted() || ($status->isSuccess() && ! $error && ! $pendiente));
        $codigo = $cdrResponse?->getCode() ?: $error?->getCode() ?: $codigoEstado;

        $guia->increment('intentos_envio');
        $guia->update([
            'estado_sunat' => $pendiente ? GuiaRemision::SUNAT_ENVIADO : ($aceptado ? GuiaRemision::SUNAT_ACEPTADO : GuiaRemision::SUNAT_RECHAZADO),
            'codigo_respuesta' => $this->codigoRespuestaSeguro($codigo, $aceptado, $pendiente),
            'mensaje_respuesta' => $this->mensajeRespuestaSeguro($mensaje),
            'enviado_at' => now(),
            'aceptado_at' => $aceptado ? now() : null,
            'rechazado_at' => (! $aceptado && ! $pendiente) ? now() : null,
        ]);

        $this->logGre($guia->refresh(), 'respuesta_gre', $ticket);

        return $this->cargar($guia);
    }

    protected function codigoRespuestaSeguro(?string $codigo, bool $aceptado = false, bool $pendiente = false): string
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return $pendiente ? '98' : ($aceptado ? '0' : 'ERROR');
        }

        return Str::limit($codigo, 20, '');
    }

    protected function mensajeRespuestaSeguro(?string $mensaje): string
    {
        $mensaje = trim((string) $mensaje);

        if ($mensaje === '') {
            return 'Respuesta GRE recibida.';
        }

        return Str::limit($mensaje, 2000, '');
    }

    protected function crearZipGre(string $xmlFilename, string $xml): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extension PHP zip es obligatoria para enviar GRE.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'gre_');
        $zip = new ZipArchive();

        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP GRE temporal.');
        }

        $zip->addFromString($xmlFilename, $xml);
        $zip->close();
        $content = file_get_contents($temp);
        @unlink($temp);

        if ($content === false) {
            throw new RuntimeException('No se pudo leer el ZIP GRE temporal.');
        }

        return $content;
    }

    protected function validarParaEnvio(GuiaRemision $guia, bool $reenvio): void
    {
        if ($guia->estado === GuiaRemision::ANULADA) {
            throw ValidationException::withMessages(['guia' => ['No se puede enviar una guia anulada.']]);
        }

        if ($guia->estado !== GuiaRemision::REGISTRADA) {
            throw ValidationException::withMessages(['guia' => ['Solo se puede enviar una guia en estado REGISTRADA.']]);
        }

        if ($guia->estado_sunat === GuiaRemision::SUNAT_ACEPTADO) {
            throw ValidationException::withMessages(['guia' => ['La guia ya fue aceptada por SUNAT.']]);
        }

        if (! $reenvio && ! in_array($guia->estado_sunat ?: GuiaRemision::SUNAT_PENDIENTE, [GuiaRemision::SUNAT_PENDIENTE, GuiaRemision::SUNAT_ERROR, GuiaRemision::SUNAT_RECHAZADO], true)) {
            throw ValidationException::withMessages(['guia' => ['La guia no esta disponible para envio SUNAT.']]);
        }

        if ($guia->detalles()->count() === 0) {
            throw ValidationException::withMessages(['detalles' => ['La guia no tiene detalles para enviar a SUNAT.']]);
        }

        $configuracion = $this->configuracionActiva($guia);
        $this->clientFactory->validarConfiguracion($configuracion);
    }

    protected function configuracionActiva(GuiaRemision $guia): SunatConfiguracion
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $guia->tenant_id)
            ->where('empresa_id', $guia->empresa_id)
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

    protected function findScoped(int $guiaId, array $scope, bool $lock = false): GuiaRemision
    {
        $query = GuiaRemision::where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($guiaId);
    }

    protected function cargar(GuiaRemision $guia): GuiaRemision
    {
        return $guia->loadMissing([
            'empresa.sunatConfiguraciones',
            'detalles.producto',
            'motivoTraslado',
            'modalidadTransporte',
            'venta',
            'comprobante',
        ]);
    }

    protected function greFilename(GuiaRemision $guia): string
    {
        return $guia->empresa->ruc.'-09-'.$guia->numero_completo;
    }

    protected function logGre(GuiaRemision $guia, string $evento, ?string $ticket = null): void
    {
        Log::info('SUNAT GRE guia remision', [
            'evento' => $evento,
            'tenant_id' => $guia->tenant_id,
            'empresa_id' => $guia->empresa_id,
            'guia_id' => $guia->id,
            'numero' => $guia->numero_completo,
            'estado_sunat' => $guia->estado_sunat,
            'codigo_respuesta' => $guia->codigo_respuesta,
            'mensaje_respuesta' => $guia->mensaje_respuesta,
            'ticket' => $ticket,
        ]);
    }

    protected function xmlPath(GuiaRemision $guia): string
    {
        return 'private/sunat/guias-remision/'.$guia->empresa_id.'/'.$guia->fecha_emision->format('Y-m-d').'/xml/'.$guia->numero_completo.'.xml';
    }

    protected function cdrPath(GuiaRemision $guia): string
    {
        return 'private/sunat/guias-remision/'.$guia->empresa_id.'/'.$guia->fecha_emision->format('Y-m-d').'/cdr/R-'.$guia->numero_completo.'.zip';
    }
}