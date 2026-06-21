<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\ComunicacionBaja;
use App\Models\SunatConfiguracion;
use App\Models\Venta;
use App\Services\Sunat\SunatClientFactory;
use App\Services\Sunat\SunatComunicacionBajaBuilder;
use Greenter\Model\Response\StatusResult;
use Greenter\Model\Response\SummaryResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ComunicacionBajaSunatService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatComunicacionBajaBuilder $builder
    ) {
    }

    public function enviar(int $comunicacionId): ComunicacionBaja
    {
        $comunicacion = $this->findScoped($comunicacionId);
        $this->validarEnvio($comunicacion, false);

        return $this->enviarConGreenter($comunicacion);
    }

    public function reenviar(int $comunicacionId): ComunicacionBaja
    {
        $comunicacion = $this->findScoped($comunicacionId);
        $this->validarEnvio($comunicacion, true);

        return $this->enviarConGreenter($comunicacion);
    }

    public function consultarTicket(int $comunicacionId): ComunicacionBaja
    {
        $comunicacion = $this->findScoped($comunicacionId);
        $this->validarConsultaTicket($comunicacion);

        try {
            $configuracion = $this->configuracionActiva($comunicacion->tenant_id, $comunicacion->empresa_id);
            $ticket = $comunicacion->ticket_sunat ?: $comunicacion->ticket;
            $response = $this->clientFactory->make($configuracion)->getStatus($ticket);

            if (! $response instanceof StatusResult) {
                throw new RuntimeException('SUNAT no devolvio una respuesta valida al consultar el ticket.');
            }

            $comunicacion = $this->actualizarEstadoDesdeTicket($comunicacion, $response);
            $this->logComunicacion($comunicacion, 'ticket_consultado');

            return $comunicacion;
        } catch (Throwable $e) {
            $comunicacion->update([
                'estado_sunat' => ComunicacionBaja::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'consultado_at' => now(),
            ]);
            $this->logComunicacion($comunicacion->refresh(), 'error_ticket');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo consultar el ticket SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    public function guardarXml(ComunicacionBaja $comunicacion, string $xml): string
    {
        $path = $this->xmlPath($comunicacion);
        Storage::disk('local')->put($path, $xml);

        return $path;
    }

    public function guardarCdr(ComunicacionBaja $comunicacion, string $cdr): string
    {
        $path = $this->cdrPath($comunicacion);
        Storage::disk('local')->put($path, $cdr);

        return $path;
    }

    public function actualizarEstadoEnvio(ComunicacionBaja $comunicacion, SummaryResult $response, string $xmlPath, string $xml): ComunicacionBaja
    {
        $ticket = $response->getTicket();

        if (! $ticket) {
            $error = $response->getError();
            $codigo = $error?->getCode();
            $mensaje = $error?->getMessage();
            $detalle = trim(($codigo ? $codigo.': ' : '').($mensaje ?: ''));

            $comunicacion->update([
                'xml_path' => $xmlPath,
                'hash' => hash('sha256', $xml),
                'codigo_respuesta' => $codigo,
                'mensaje_respuesta' => $detalle ?: 'SUNAT no devolvio ticket para la comunicacion de baja.',
                'enviado_at' => now(),
            ]);

            throw new RuntimeException($detalle ? 'SUNAT no devolvio ticket para la comunicacion de baja. '.$detalle : 'SUNAT no devolvio ticket para la comunicacion de baja.');
        }

        $comunicacion->update([
            'xml_path' => $xmlPath,
            'hash' => hash('sha256', $xml),
            'estado_sunat' => ComunicacionBaja::ENVIADO,
            'ticket' => $ticket,
            'ticket_sunat' => $ticket,
            'codigo_respuesta' => $response->getError()?->getCode(),
            'mensaje_respuesta' => $response->getError()?->getMessage() ?: 'Comunicacion de baja enviada a SUNAT. Consulte el ticket para obtener el CDR.',
            'enviado_at' => now(),
        ]);

        return $this->cargarComunicacion($comunicacion->refresh());
    }

    public function actualizarEstadoDesdeTicket(ComunicacionBaja $comunicacion, StatusResult $response): ComunicacionBaja
    {
        $cdrResponse = $response->getCdrResponse();
        $error = $response->getError();
        $codigo = $cdrResponse?->getCode() ?? $response->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Ticket consultado correctamente.';
        $estado = ComunicacionBaja::ENVIADO;
        $now = now();
        $cdrPath = $comunicacion->cdr_path;

        if ($response->getCode() === '98') {
            $mensaje = 'La comunicacion de baja sigue en proceso en SUNAT.';
        } elseif (($cdrResponse && $cdrResponse->isAccepted()) || $response->isSuccess()) {
            $estado = ComunicacionBaja::ACEPTADO;
        } elseif ($response->getCode() === '99' || $cdrResponse || $error) {
            $estado = ComunicacionBaja::RECHAZADO;
        }

        if ($response->getCdrZip()) {
            $cdrPath = $this->guardarCdr($comunicacion, $response->getCdrZip());
        }

        $comunicacion->update([
            'cdr_path' => $cdrPath,
            'estado_sunat' => $estado,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'consultado_at' => $now,
            'aceptado_at' => $estado === ComunicacionBaja::ACEPTADO ? $now : $comunicacion->aceptado_at,
            'rechazado_at' => $estado === ComunicacionBaja::RECHAZADO ? $now : $comunicacion->rechazado_at,
        ]);

        $this->actualizarComprobantesIncluidos($comunicacion->refresh(), $estado, $now);

        return $this->cargarComunicacion($comunicacion->refresh());
    }

    protected function enviarConGreenter(ComunicacionBaja $comunicacion): ComunicacionBaja
    {
        try {
            $configuracion = $this->configuracionActiva($comunicacion->tenant_id, $comunicacion->empresa_id);
            $see = $this->clientFactory->make($configuracion);
            $voided = $this->builder->buildFromComunicacion($comunicacion);
            $xml = $see->getXmlSigned($voided);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado de la comunicacion de baja.');
            }

            $xmlPath = $this->guardarXml($comunicacion, $xml);
            $response = $see->sendXml($voided::class, $voided->getName(), $xml);

            if (! $response instanceof SummaryResult) {
                throw new RuntimeException('SUNAT no devolvio una respuesta valida para la comunicacion de baja.');
            }

            $comunicacion->increment('intentos_envio');
            $comunicacion = $this->actualizarEstadoEnvio($comunicacion, $response, $xmlPath, $xml);
            $this->logComunicacion($comunicacion, 'enviado');

            return $comunicacion;
        } catch (Throwable $e) {
            $comunicacion->increment('intentos_envio');
            $comunicacion->update([
                'estado_sunat' => ComunicacionBaja::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
            ]);
            $this->logComunicacion($comunicacion->refresh(), 'error');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo enviar la comunicacion de baja a SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    protected function validarEnvio(ComunicacionBaja $comunicacion, bool $reenvio): void
    {
        if ($comunicacion->estado !== ComunicacionBaja::REGISTRADA) {
            throw ValidationException::withMessages(['estado' => ['Solo se puede enviar una comunicacion de baja en estado REGISTRADA.']]);
        }

        if ($comunicacion->estado === ComunicacionBaja::ANULADA) {
            throw ValidationException::withMessages(['estado' => ['No se puede enviar una comunicacion de baja anulada.']]);
        }

        if (($comunicacion->ticket_sunat ?: $comunicacion->ticket) && $comunicacion->estado_sunat === ComunicacionBaja::ENVIADO) {
            throw ValidationException::withMessages(['estado_sunat' => [$reenvio ? 'No se puede reenviar una comunicacion de baja que ya tiene ticket SUNAT.' : 'La comunicacion de baja ya fue enviada y tiene ticket SUNAT.']]);
        }

        $comunicacion->loadMissing('detalles');

        if ($comunicacion->detalles->count() === 0) {
            throw ValidationException::withMessages(['detalles' => ['La comunicacion de baja no tiene detalles para enviar.']]);
        }

        $tieneBoletas = $comunicacion->detalles->contains(function ($detalle) {
            return in_array($detalle->tipo_documento, [Venta::BOLETA, 'BOLETA', '03'], true);
        });

        if ($tieneBoletas) {
            throw ValidationException::withMessages([
                'detalles' => ['SUNAT no acepta BOLETAS en Comunicacion de Baja (RA). Las boletas se informan mediante Resumen Diario. Anula esta comunicacion y genera una nueva solo con facturas o notas permitidas.'],
            ]);
        }

        $this->configuracionActiva($comunicacion->tenant_id, $comunicacion->empresa_id);
    }

    protected function validarConsultaTicket(ComunicacionBaja $comunicacion): void
    {
        if (! ($comunicacion->ticket_sunat ?: $comunicacion->ticket)) {
            throw ValidationException::withMessages(['ticket_sunat' => ['La comunicacion de baja no tiene ticket SUNAT para consultar.']]);
        }

        if ($comunicacion->estado === ComunicacionBaja::ANULADA) {
            throw ValidationException::withMessages(['estado' => ['No se puede consultar una comunicacion de baja anulada.']]);
        }

        if ($comunicacion->estado_sunat === ComunicacionBaja::ACEPTADO) {
            throw ValidationException::withMessages(['estado_sunat' => ['La comunicacion de baja ya fue aceptada por SUNAT.']]);
        }

        $this->configuracionActiva($comunicacion->tenant_id, $comunicacion->empresa_id);
    }

    protected function actualizarComprobantesIncluidos(ComunicacionBaja $comunicacion, string $estado, mixed $fecha): void
    {
        $comunicacion->loadMissing('detalles.comprobante');

        foreach ($comunicacion->detalles as $detalle) {
            $comprobante = $detalle->comprobante ?: $detalle->comprobanteElectronico;
            if (! $comprobante) {
                continue;
            }

            if ($estado === ComunicacionBaja::ACEPTADO) {
                $comprobante->update([
                    'estado_baja' => ComprobanteElectronico::BAJA_ACEPTADA,
                    'estado_sunat' => ComprobanteElectronico::DADO_DE_BAJA,
                    'dado_baja_at' => $fecha,
                    'comunicacion_baja_id' => $comunicacion->id,
                ]);
            } elseif ($estado === ComunicacionBaja::RECHAZADO) {
                $comprobante->update([
                    'estado_baja' => ComprobanteElectronico::BAJA_RECHAZADA,
                    'comunicacion_baja_id' => $comunicacion->id,
                ]);
            }
        }
    }

    protected function configuracionActiva(int $tenantId, int $empresaId): SunatConfiguracion
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->first();

        if (! $configuracion) {
            throw ValidationException::withMessages(['sunat_configuracion' => ['No existe configuracion SUNAT activa para esta empresa.']]);
        }

        return $configuracion;
    }

    protected function findScoped(int $comunicacionId): ComunicacionBaja
    {
        $request = request();

        return ComunicacionBaja::with(['empresa.sunatConfiguraciones', 'detalles.comprobante', 'detalles.comprobanteElectronico'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($comunicacionId);
    }

    protected function cargarComunicacion(ComunicacionBaja $comunicacion): ComunicacionBaja
    {
        return $comunicacion->load(['detalles.comprobante', 'tienda', 'creadoPor'])->loadCount('detalles');
    }

    protected function xmlPath(ComunicacionBaja $comunicacion): string
    {
        return 'private/sunat/comunicaciones-baja/'.$comunicacion->empresa_id.'/'.$comunicacion->fecha_baja->format('Y-m-d').'/xml/'.$comunicacion->identificador.'.xml';
    }

    protected function cdrPath(ComunicacionBaja $comunicacion): string
    {
        return 'private/sunat/comunicaciones-baja/'.$comunicacion->empresa_id.'/'.$comunicacion->fecha_baja->format('Y-m-d').'/cdr/R-'.$comunicacion->identificador.'.zip';
    }

    protected function logComunicacion(ComunicacionBaja $comunicacion, string $evento): void
    {
        Log::info('SUNAT comunicacion baja', [
            'evento' => $evento,
            'tenant_id' => $comunicacion->tenant_id,
            'empresa_id' => $comunicacion->empresa_id,
            'tienda_id' => $comunicacion->tienda_id,
            'comunicacion_id' => $comunicacion->id,
            'identificador' => $comunicacion->identificador,
            'estado_sunat' => $comunicacion->estado_sunat,
            'codigo_respuesta' => $comunicacion->codigo_respuesta,
            'mensaje_respuesta' => $comunicacion->mensaje_respuesta,
            'ticket' => $comunicacion->ticket_sunat ?: $comunicacion->ticket,
        ]);
    }
}
