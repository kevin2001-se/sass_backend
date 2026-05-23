<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Models\NotaElectronica;
use App\Models\ResumenDiario;
use App\Models\ResumenDiarioDetalle;
use App\Models\SunatConfiguracion;
use App\Models\Venta;
use Carbon\Carbon;
use Greenter\Model\Response\StatusResult;
use Greenter\Model\Response\SummaryResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ResumenDiarioService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatResumenDiarioBuilder $builder
    ) {
    }

    public function generarResumen(array $data): ResumenDiario
    {
        return DB::transaction(function () use ($data) {
            $fechaResumen = Carbon::parse($data['fecha_resumen'])->toDateString();
            $fechaEnvio = now()->toDateString();
            $documentos = $this->obtenerDocumentosParaResumen($fechaResumen, $data['tienda_id'], $data);

            if ($documentos->isEmpty()) {
                throw ValidationException::withMessages(['documentos' => ['No hay boletas ni notas de boleta pendientes para resumir en la fecha indicada.']]);
            }

            $this->configuracionActiva($data['tenant_id'], $data['empresa_id']);

            $correlativo = $this->siguienteCorrelativo($data['empresa_id'], $data['tienda_id'], $fechaEnvio);
            $identificador = $this->formatearIdentificador($fechaEnvio, $correlativo);

            $resumen = ResumenDiario::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'fecha_resumen' => $fechaResumen,
                'fecha_envio' => $fechaEnvio,
                'correlativo' => $correlativo,
                'identificador' => $identificador,
                'estado_sunat' => ResumenDiario::PENDIENTE,
            ]);

            foreach ($documentos as $documento) {
                $totales = $this->totalesDocumento($documento);

                ResumenDiarioDetalle::create([
                    'tenant_id' => $resumen->tenant_id,
                    'empresa_id' => $resumen->empresa_id,
                    'tienda_id' => $resumen->tienda_id,
                    'resumen_diario_id' => $resumen->id,
                    'comprobante_electronico_id' => $documento->id,
                    'tipo_documento' => $this->tipoDocumentoSunat($documento),
                    'serie' => $documento->serie,
                    'correlativo' => $documento->correlativo,
                    'numero_comprobante' => $documento->numero_comprobante,
                    'estado_item' => $this->estadoItemDocumento($documento),
                    'total' => $totales['total'],
                    'total_igv' => $totales['total_igv'],
                ]);
            }

            return $this->cargarResumen($resumen);
        });
    }

    public function enviarResumen(int $resumenId, array $scope): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId, $scope);

        if ($resumen->estado_sunat === ResumenDiario::ACEPTADO) {
            throw ValidationException::withMessages(['resumen' => ['El resumen diario ya fue aceptado por SUNAT.']]);
        }

        try {
            $configuracion = $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
            $see = $this->clientFactory->make($configuracion);
            $summary = $this->builder->buildFromResumen($resumen);
            $xml = $see->getXmlSigned($summary);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado del resumen.');
            }

            $this->guardarXmlFirmado($resumen, $xml);
            $response = $see->sendXml($summary::class, $summary->getName(), $xml);

            if (! $response instanceof SummaryResult) {
                throw new RuntimeException('SUNAT no devolvio ticket para el resumen diario.');
            }

            $resumen->increment('intentos_envio');
            $resumen->update([
                'estado_sunat' => ResumenDiario::ENVIADO,
                'ticket' => $response->getTicket(),
                'codigo_respuesta' => $response->getError()?->getCode(),
                'mensaje_respuesta' => $response->getError()?->getMessage() ?: 'Resumen enviado a SUNAT. Consulte el ticket para obtener el CDR.',
                'enviado_at' => now(),
            ]);

            return $this->cargarResumen($resumen->refresh());
        } catch (Throwable $e) {
            $resumen->increment('intentos_envio');
            $resumen->update([
                'estado_sunat' => ResumenDiario::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
                'observacion' => trim(($resumen->observacion ? $resumen->observacion.' | ' : '').'ERROR: '.$e->getMessage()),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo enviar el resumen diario a SUNAT. '.$e->getMessage()]]);
        }
    }

    public function consultarTicket(int $resumenId, array $scope): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId, $scope);

        if (! $resumen->ticket) {
            throw ValidationException::withMessages(['ticket' => ['El resumen diario no tiene ticket SUNAT para consultar.']]);
        }

        try {
            $configuracion = $this->configuracionActiva($resumen->tenant_id, $resumen->empresa_id);
            $response = $this->clientFactory->make($configuracion)->getStatus($resumen->ticket);

            return $this->actualizarEstadoDesdeTicket($resumen, $response);
        } catch (Throwable $e) {
            $resumen->update([
                'estado_sunat' => ResumenDiario::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo consultar el ticket SUNAT. '.$e->getMessage()]]);
        }
    }

    public function reenviarResumen(int $resumenId, array $scope): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId, $scope);

        if ($resumen->estado_sunat === ResumenDiario::ACEPTADO) {
            throw ValidationException::withMessages(['resumen' => ['El resumen diario ya fue aceptado por SUNAT.']]);
        }

        return $this->enviarResumen($resumen->id, $scope);
    }

    public function generarIdentificador(int $empresaId, int $tiendaId, string $fechaEnvio): string
    {
        $correlativo = $this->siguienteCorrelativo($empresaId, $tiendaId, Carbon::parse($fechaEnvio)->toDateString());

        return $this->formatearIdentificador($fechaEnvio, $correlativo);
    }

    public function obtenerDocumentosParaResumen(string $fechaResumen, int $tiendaId, array $filtros = []): Collection
    {
        $incluirBoletas = (bool) ($filtros['incluir_boletas'] ?? true);
        $incluirNotasCredito = (bool) ($filtros['incluir_notas_credito'] ?? true);
        $incluirNotasDebito = (bool) ($filtros['incluir_notas_debito'] ?? true);
        $tipos = [];

        if ($incluirBoletas) {
            $tipos[] = Venta::BOLETA;
        }
        if ($incluirNotasCredito) {
            $tipos[] = NotaElectronica::NOTA_CREDITO;
        }
        if ($incluirNotasDebito) {
            $tipos[] = NotaElectronica::NOTA_DEBITO;
        }

        if (empty($tipos)) {
            return new Collection();
        }

        $accionesAceptadas = ResumenDiarioDetalle::whereHas('resumenDiario', function ($query) use ($filtros, $tiendaId) {
            $query->where('estado_sunat', ResumenDiario::ACEPTADO)
                ->where('tienda_id', $tiendaId)
                ->when(isset($filtros['tenant_id']), fn ($subquery) => $subquery->where('tenant_id', $filtros['tenant_id']))
                ->when(isset($filtros['empresa_id']), fn ($subquery) => $subquery->where('empresa_id', $filtros['empresa_id']));
        })->get(['comprobante_electronico_id', 'estado_item'])
            ->mapWithKeys(fn ($detalle) => [$detalle->comprobante_electronico_id.'-'.$detalle->estado_item => true]);

        return ComprobanteElectronico::with([
            'venta.cliente',
            'notaElectronica.venta.cliente',
            'notaElectronica.comprobanteReferencia',
        ])
            ->where('tienda_id', $tiendaId)
            ->when(isset($filtros['tenant_id']), fn ($query) => $query->where('tenant_id', $filtros['tenant_id']))
            ->when(isset($filtros['empresa_id']), fn ($query) => $query->where('empresa_id', $filtros['empresa_id']))
            ->whereDate('fecha_emision', $fechaResumen)
            ->whereIn('estado_sunat', [ComprobanteElectronico::ACEPTADO, ComprobanteElectronico::PENDIENTE])
            ->whereIn('tipo_comprobante', $tipos)
            ->whereNotIn('id', $excluidos)
            ->where(function ($query) {
                $query->where('tipo_comprobante', Venta::BOLETA)
                    ->orWhereHas('notaElectronica.comprobanteReferencia', function ($subquery) {
                        $subquery->where('tipo_comprobante', Venta::BOLETA);
                    });
            })
            ->orderBy('serie')
            ->orderBy('correlativo')
            ->get()
            ->filter(fn (ComprobanteElectronico $documento) => ! isset($accionesAceptadas[$documento->id.'-'.$this->estadoItemDocumento($documento)]))
            ->values();
    }

    protected function actualizarEstadoDesdeTicket(ResumenDiario $resumen, StatusResult $response): ResumenDiario
    {
        $cdrResponse = $response->getCdrResponse();
        $error = $response->getError();
        $codigo = $cdrResponse?->getCode() ?? $response->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Respuesta SUNAT recibida.';
        $estado = ResumenDiario::ENVIADO;

        if ($response->getCode() === '98') {
            $mensaje = 'El resumen diario sigue en proceso en SUNAT.';
        } elseif (($cdrResponse && $cdrResponse->isAccepted()) || $response->isSuccess()) {
            $estado = ResumenDiario::ACEPTADO;
        } elseif ($response->getCode() === '99' || $cdrResponse || $error) {
            $estado = ResumenDiario::RECHAZADO;
        }

        if ($response->getCdrZip()) {
            $this->guardarCdr($resumen, $response->getCdrZip());
        }

        $resumen->update([
            'estado_sunat' => $estado,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'aceptado_at' => $estado === ResumenDiario::ACEPTADO ? now() : $resumen->aceptado_at,
            'rechazado_at' => $estado === ResumenDiario::RECHAZADO ? now() : $resumen->rechazado_at,
        ]);

        return $this->cargarResumen($resumen->refresh());
    }

    protected function findScoped(int $resumenId, array $scope): ResumenDiario
    {
        return ResumenDiario::with(['detalles.comprobanteElectronico'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($resumenId);
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

    protected function siguienteCorrelativo(int $empresaId, int $tiendaId, string $fechaEnvio): int
    {
        $ultimo = ResumenDiario::where('empresa_id', $empresaId)
            ->where('tienda_id', $tiendaId)
            ->whereDate('fecha_envio', $fechaEnvio)
            ->lockForUpdate()
            ->max('correlativo');

        return ((int) $ultimo) + 1;
    }

    protected function formatearIdentificador(string $fechaEnvio, int $correlativo): string
    {
        return 'RC-'.Carbon::parse($fechaEnvio)->format('Ymd').'-'.str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }

    protected function totalesDocumento(ComprobanteElectronico $documento): array
    {
        if ($documento->tipo_comprobante === Venta::BOLETA) {
            return [
                'total' => round((float) $documento->venta->total, 2),
                'total_igv' => round((float) $documento->venta->total_igv, 2),
            ];
        }

        return [
            'total' => round((float) $documento->notaElectronica->total, 2),
            'total_igv' => round((float) $documento->notaElectronica->total_igv, 2),
        ];
    }

    protected function tipoDocumentoSunat(ComprobanteElectronico $documento): string
    {
        return match ($documento->tipo_comprobante) {
            Venta::BOLETA => '03',
            NotaElectronica::NOTA_CREDITO => '07',
            NotaElectronica::NOTA_DEBITO => '08',
            default => throw ValidationException::withMessages(['tipo_comprobante' => ['Documento no permitido en resumen diario.']]),
        };
    }

    protected function estadoItemDocumento(ComprobanteElectronico $documento): string
    {
        if ($documento->tipo_comprobante === Venta::BOLETA && $documento->venta?->estado === Venta::ANULADA) {
            return ResumenDiarioDetalle::ANULAR;
        }

        if (in_array($documento->tipo_comprobante, [NotaElectronica::NOTA_CREDITO, NotaElectronica::NOTA_DEBITO], true)
            && $documento->notaElectronica?->estado === NotaElectronica::ANULADA) {
            return ResumenDiarioDetalle::ANULAR;
        }

        return ResumenDiarioDetalle::ADICIONAR;
    }

    protected function guardarXmlFirmado(ResumenDiario $resumen, string $xml): void
    {
        Storage::disk('local')->put($this->xmlPath($resumen), $xml);
        $resumen->update(['xml_path' => $this->xmlPath($resumen)]);
    }

    protected function guardarCdr(ResumenDiario $resumen, string $cdr): void
    {
        Storage::disk('local')->put($this->cdrPath($resumen), $cdr);
        $resumen->update(['cdr_path' => $this->cdrPath($resumen)]);
    }

    protected function xmlPath(ResumenDiario $resumen): string
    {
        return 'private/sunat/resumenes/'.$resumen->empresa_id.'/'.$resumen->fecha_envio->format('Y-m-d').'/xml/'.$resumen->identificador.'.xml';
    }

    protected function cdrPath(ResumenDiario $resumen): string
    {
        return 'private/sunat/resumenes/'.$resumen->empresa_id.'/'.$resumen->fecha_envio->format('Y-m-d').'/cdr/R-'.$resumen->identificador.'.zip';
    }

    protected function cargarResumen(ResumenDiario $resumen): ResumenDiario
    {
        return $resumen->load(['detalles.comprobanteElectronico'])->loadCount('detalles');
    }
}
