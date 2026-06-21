<?php

namespace App\Services;

use App\Models\ComprobanteElectronico;
use App\Models\ResumenDiario;
use App\Models\ResumenDiarioDetalle;
use App\Models\SunatConfiguracion;
use App\Services\Sunat\SunatClientFactory;
use App\Services\Sunat\SunatResumenDiarioBuilder;
use Greenter\Model\Response\StatusResult;
use Greenter\Model\Response\SummaryResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ResumenDiarioSunatService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatResumenDiarioBuilder $builder
    ) {
    }

    public function enviar(int $resumenId): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId);
        $this->validarEnvio($resumen, false);

        return $this->enviarConGreenter($resumen);
    }

    public function reenviar(int $resumenId): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId);
        $this->validarEnvio($resumen, true);

        return $this->enviarConGreenter($resumen);
    }

    public function consultarTicket(int $resumenId): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId);
        $this->validarConsultaTicket($resumen);

        try {
            $configuracion = $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
            $ticket = $resumen->ticket_sunat ?: $resumen->ticket;
            $response = $this->clientFactory->make($configuracion)->getStatus($ticket);

            if (! $response instanceof StatusResult) {
                throw new RuntimeException('SUNAT no devolvio una respuesta valida al consultar el ticket.');
            }

            $resumen = $this->actualizarEstadoDesdeTicket($resumen, $response);
            $this->logResumen($resumen, 'ticket_consultado');

            return $resumen;
        } catch (Throwable $e) {
            $resumen->update([
                'estado_sunat' => ResumenDiario::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'consultado_at' => now(),
            ]);
            $this->logResumen($resumen->refresh(), 'error_ticket');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo consultar el ticket SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    public function guardarXml(ResumenDiario $resumen, string $xml): string
    {
        $path = $this->xmlPath($resumen);
        Storage::disk('local')->put($path, $xml);

        return $path;
    }

    public function guardarCdr(ResumenDiario $resumen, string $cdr): string
    {
        $path = $this->cdrPath($resumen);
        Storage::disk('local')->put($path, $cdr);

        return $path;
    }

    public function actualizarEstadoEnvio(ResumenDiario $resumen, SummaryResult $response, string $xmlPath, string $xml): ResumenDiario
    {
        $ticket = $response->getTicket();

        if (! $ticket) {
            throw new RuntimeException('SUNAT no devolvio ticket para el resumen diario.');
        }

        $resumen->update([
            'xml_path' => $xmlPath,
            'hash' => hash('sha256', $xml),
            'estado_sunat' => ResumenDiario::ENVIADO,
            'ticket' => $ticket,
            'ticket_sunat' => $ticket,
            'codigo_respuesta' => $response->getError()?->getCode(),
            'mensaje_respuesta' => $response->getError()?->getMessage() ?: 'Resumen enviado a SUNAT. Consulte el ticket para obtener el CDR.',
            'enviado_at' => now(),
        ]);

        return $this->cargarResumen($resumen->refresh());
    }

    public function actualizarEstadoDesdeTicket(ResumenDiario $resumen, StatusResult $response): ResumenDiario
    {
        $cdrResponse = $response->getCdrResponse();
        $error = $response->getError();
        $codigo = $cdrResponse?->getCode() ?? $response->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Ticket consultado correctamente.';
        $estado = ResumenDiario::ENVIADO;
        $now = now();
        $cdrPath = $resumen->cdr_path;

        if ($response->getCode() === '98') {
            $mensaje = 'El resumen diario sigue en proceso en SUNAT.';
        } elseif (($cdrResponse && $cdrResponse->isAccepted()) || $response->isSuccess()) {
            $estado = ResumenDiario::ACEPTADO;
        } elseif ($response->getCode() === '99' || $cdrResponse || $error) {
            $estado = ResumenDiario::RECHAZADO;
        }

        if ($response->getCdrZip()) {
            $cdrPath = $this->guardarCdr($resumen, $response->getCdrZip());
        }

        $resumen->update([
            'cdr_path' => $cdrPath,
            'estado_sunat' => $estado,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'consultado_at' => $now,
            'aceptado_at' => $estado === ResumenDiario::ACEPTADO ? $now : $resumen->aceptado_at,
            'rechazado_at' => $estado === ResumenDiario::RECHAZADO ? $now : $resumen->rechazado_at,
        ]);

        $resumen = $resumen->refresh();
        $this->actualizarBajasIncluidas($resumen, $estado);

        return $this->cargarResumen($resumen);
    }

    protected function actualizarBajasIncluidas(ResumenDiario $resumen, string $estadoSunat): void
    {
        $resumen->loadMissing('detalles');

        foreach ($resumen->detalles->where('accion', ResumenDiarioDetalle::ACCION_BAJA) as $detalle) {
            if (! $detalle->comprobante_electronico_id) {
                continue;
            }

            if ($estadoSunat === ResumenDiario::ACEPTADO) {
                ComprobanteElectronico::whereKey($detalle->comprobante_electronico_id)->update([
                    'estado_baja' => ComprobanteElectronico::BAJA_ACEPTADA,
                    'estado_sunat' => ComprobanteElectronico::DADO_DE_BAJA,
                    'dado_baja_at' => now(),
                ]);
            } elseif ($estadoSunat === ResumenDiario::RECHAZADO) {
                ComprobanteElectronico::whereKey($detalle->comprobante_electronico_id)->update([
                    'estado_baja' => ComprobanteElectronico::BAJA_RECHAZADA,
                ]);
            }
        }
    }

    protected function enviarConGreenter(ResumenDiario $resumen): ResumenDiario
    {
        try {
            $configuracion = $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
            $see = $this->clientFactory->make($configuracion);
            $summary = $this->builder->buildFromResumen($resumen);
            $xml = $see->getXmlSigned($summary);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado del resumen diario.');
            }

            $xmlPath = $this->guardarXml($resumen, $xml);
            $response = $see->sendXml($summary::class, $summary->getName(), $xml);

            if (! $response instanceof SummaryResult) {
                throw new RuntimeException('SUNAT no devolvio una respuesta valida para el resumen diario.');
            }

            $resumen->increment('intentos_envio');
            $resumen = $this->actualizarEstadoEnvio($resumen, $response, $xmlPath, $xml);
            $this->logResumen($resumen, 'enviado');

            return $resumen;
        } catch (Throwable $e) {
            $resumen->increment('intentos_envio');
            $resumen->update([
                'estado_sunat' => ResumenDiario::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
            ]);
            $this->logResumen($resumen->refresh(), 'error');

            throw ValidationException::withMessages([
                'sunat' => ['No se pudo enviar el resumen diario a SUNAT. '.$e->getMessage()],
            ]);
        }
    }

    protected function validarEnvio(ResumenDiario $resumen, bool $reenvio): void
    {
        if ($resumen->estado !== ResumenDiario::REGISTRADO) {
            throw ValidationException::withMessages(['estado' => ['Solo se puede enviar un resumen diario en estado REGISTRADO.']]);
        }

        if ($resumen->estado === ResumenDiario::ANULADO) {
            throw ValidationException::withMessages(['estado' => ['No se puede enviar un resumen diario anulado.']]);
        }

        if (($resumen->ticket_sunat || $resumen->ticket) && $resumen->estado_sunat === ResumenDiario::ENVIADO) {
            throw ValidationException::withMessages(['estado_sunat' => [$reenvio ? 'No se puede reenviar un resumen diario que ya tiene ticket SUNAT.' : 'El resumen diario ya fue enviado y tiene ticket SUNAT.']]);
        }

        if ($resumen->detalles()->count() === 0) {
            throw ValidationException::withMessages(['detalles' => ['El resumen diario no tiene detalles para enviar.']]);
        }

        $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
    }

    protected function validarConsultaTicket(ResumenDiario $resumen): void
    {
        if (! ($resumen->ticket_sunat ?: $resumen->ticket)) {
            throw ValidationException::withMessages(['ticket_sunat' => ['El resumen diario no tiene ticket SUNAT para consultar.']]);
        }

        if ($resumen->estado === ResumenDiario::ANULADO) {
            throw ValidationException::withMessages(['estado' => ['No se puede consultar un resumen diario anulado.']]);
        }

        if ($resumen->estado_sunat === ResumenDiario::ACEPTADO) {
            throw ValidationException::withMessages(['estado_sunat' => ['El resumen diario ya fue aceptado por SUNAT.']]);
        }

        $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
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

    protected function findScoped(int $resumenId): ResumenDiario
    {
        $request = request();

        return ResumenDiario::with(['empresa.sunatConfiguraciones', 'detalles'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($resumenId);
    }

    protected function cargarResumen(ResumenDiario $resumen): ResumenDiario
    {
        return $resumen->load(['detalles', 'tienda', 'creadoPor'])->loadCount('detalles');
    }

    protected function xmlPath(ResumenDiario $resumen): string
    {
        return 'private/sunat/resumenes-diarios/'.$resumen->empresa_id.'/'.$resumen->fecha_resumen->format('Y-m-d').'/xml/'.$resumen->identificador.'.xml';
    }

    protected function cdrPath(ResumenDiario $resumen): string
    {
        return 'private/sunat/resumenes-diarios/'.$resumen->empresa_id.'/'.$resumen->fecha_resumen->format('Y-m-d').'/cdr/R-'.$resumen->identificador.'.zip';
    }

    protected function logResumen(ResumenDiario $resumen, string $evento): void
    {
        Log::info('SUNAT resumen diario', [
            'evento' => $evento,
            'tenant_id' => $resumen->tenant_id,
            'empresa_id' => $resumen->empresa_id,
            'tienda_id' => $resumen->tienda_id,
            'resumen_id' => $resumen->id,
            'identificador' => $resumen->identificador,
            'estado_sunat' => $resumen->estado_sunat,
            'codigo_respuesta' => $resumen->codigo_respuesta,
            'mensaje_respuesta' => $resumen->mensaje_respuesta,
            'ticket' => $resumen->ticket_sunat ?: $resumen->ticket,
        ]);
    }
}