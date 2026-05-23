<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Models\ComunicacionBaja;
use App\Models\ComunicacionBajaDetalle;
use App\Models\NotaElectronica;
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

class ComunicacionBajaService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatComunicacionBajaBuilder $builder
    ) {
    }

    public function generarBaja(array $data): ComunicacionBaja
    {
        return DB::transaction(function () use ($data) {
            $fechaBaja = Carbon::parse($data['fecha_baja'])->toDateString();
            $fechaEnvio = now()->toDateString();
            $ids = collect($data['comprobantes'])->pluck('comprobante_electronico_id')->map(fn ($id) => (int) $id)->all();
            $motivos = collect($data['comprobantes'])->mapWithKeys(fn ($item) => [(int) $item['comprobante_electronico_id'] => $item['motivo_baja']]);
            $documentos = $this->validarDocumentosParaBaja($ids, $data['tienda_id'], $data);
            $this->validarFechaBaja($documentos, $fechaBaja);

            $this->configuracionActiva($data['tenant_id'], $data['empresa_id']);

            $correlativo = $this->siguienteCorrelativo($data['empresa_id'], $data['tienda_id'], $fechaEnvio);
            $identificador = $this->formatearIdentificador($fechaEnvio, $correlativo);

            $baja = ComunicacionBaja::create([
                'tenant_id' => $data['tenant_id'],
                'empresa_id' => $data['empresa_id'],
                'tienda_id' => $data['tienda_id'],
                'fecha_baja' => $fechaBaja,
                'fecha_envio' => $fechaEnvio,
                'correlativo' => $correlativo,
                'identificador' => $identificador,
                'estado_sunat' => ComunicacionBaja::PENDIENTE,
                'observacion' => $data['observacion'] ?? null,
            ]);

            foreach ($documentos as $documento) {
                ComunicacionBajaDetalle::create([
                    'tenant_id' => $baja->tenant_id,
                    'empresa_id' => $baja->empresa_id,
                    'tienda_id' => $baja->tienda_id,
                    'comunicacion_baja_id' => $baja->id,
                    'comprobante_electronico_id' => $documento->id,
                    'tipo_documento' => $this->tipoDocumentoSunat($documento),
                    'serie' => $documento->serie,
                    'correlativo' => $documento->correlativo,
                    'numero_comprobante' => $documento->numero_comprobante,
                    'motivo_baja' => $motivos[$documento->id],
                ]);
            }

            return $this->cargarBaja($baja);
        });
    }

    public function enviarBaja(int $bajaId, array $scope): ComunicacionBaja
    {
        $baja = $this->findScoped($bajaId, $scope);

        if ($baja->estado_sunat === ComunicacionBaja::ACEPTADO) {
            throw ValidationException::withMessages(['baja' => ['La comunicacion de baja ya fue aceptada por SUNAT.']]);
        }

        try {
            $configuracion = $this->configuracionActiva($baja->tenant_id, $baja->empresa_id);
            $see = $this->clientFactory->make($configuracion);
            $voided = $this->builder->buildFromBaja($baja);
            $xml = $see->getXmlSigned($voided);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado de la comunicacion de baja.');
            }

            $this->guardarXmlFirmado($baja, $xml);
            $response = $see->sendXml($voided::class, $voided->getName(), $xml);

            if (! $response instanceof SummaryResult) {
                throw new RuntimeException('SUNAT no devolvio ticket para la comunicacion de baja.');
            }

            $baja->increment('intentos_envio');
            $baja->update([
                'estado_sunat' => ComunicacionBaja::ENVIADO,
                'ticket' => $response->getTicket(),
                'codigo_respuesta' => $response->getError()?->getCode(),
                'mensaje_respuesta' => $response->getError()?->getMessage() ?: 'Comunicacion de baja enviada a SUNAT. Consulte el ticket para obtener el CDR.',
                'enviado_at' => now(),
            ]);

            return $this->cargarBaja($baja->refresh());
        } catch (Throwable $e) {
            $baja->increment('intentos_envio');
            $baja->update([
                'estado_sunat' => ComunicacionBaja::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
                'observacion' => trim(($baja->observacion ? $baja->observacion.' | ' : '').'ERROR: '.$e->getMessage()),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo enviar la comunicacion de baja a SUNAT. '.$e->getMessage()]]);
        }
    }

    public function consultarTicket(int $bajaId, array $scope): ComunicacionBaja
    {
        $baja = $this->findScoped($bajaId, $scope);

        if (! $baja->ticket) {
            throw ValidationException::withMessages(['ticket' => ['La comunicacion de baja no tiene ticket SUNAT para consultar.']]);
        }

        try {
            $configuracion = $this->configuracionActiva($baja->tenant_id, $baja->empresa_id);
            $response = $this->clientFactory->make($configuracion)->getStatus($baja->ticket);

            return $this->actualizarEstadoDesdeTicket($baja, $response);
        } catch (Throwable $e) {
            $baja->update([
                'estado_sunat' => ComunicacionBaja::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo consultar el ticket SUNAT. '.$e->getMessage()]]);
        }
    }

    public function reenviarBaja(int $bajaId, array $scope): ComunicacionBaja
    {
        $baja = $this->findScoped($bajaId, $scope);

        if ($baja->estado_sunat === ComunicacionBaja::ACEPTADO) {
            throw ValidationException::withMessages(['baja' => ['La comunicacion de baja ya fue aceptada por SUNAT.']]);
        }

        return $this->enviarBaja($baja->id, $scope);
    }

    public function generarIdentificador(int $empresaId, int $tiendaId, string $fechaEnvio): string
    {
        $correlativo = $this->siguienteCorrelativo($empresaId, $tiendaId, Carbon::parse($fechaEnvio)->toDateString());

        return $this->formatearIdentificador($fechaEnvio, $correlativo);
    }

    public function validarDocumentosParaBaja(array $comprobanteIds, int $tiendaId, array $scope = []): Collection
    {
        $documentos = ComprobanteElectronico::with(['venta', 'notaElectronica'])
            ->whereIn('id', $comprobanteIds)
            ->where('tienda_id', $tiendaId)
            ->when(isset($scope['tenant_id']), fn ($query) => $query->where('tenant_id', $scope['tenant_id']))
            ->when(isset($scope['empresa_id']), fn ($query) => $query->where('empresa_id', $scope['empresa_id']))
            ->lockForUpdate()
            ->get();

        if ($documentos->count() !== count(array_unique($comprobanteIds))) {
            throw ValidationException::withMessages(['comprobantes' => ['Uno o mas comprobantes no pertenecen a la tienda activa o no existen.']]);
        }

        foreach ($documentos as $documento) {
            if ($documento->estado_sunat !== ComprobanteElectronico::ACEPTADO) {
                throw ValidationException::withMessages(['comprobantes' => ['Solo se pueden dar de baja comprobantes ACEPTADOS.']]);
            }

            if ($documento->tipo_comprobante === Venta::NOTA_VENTA) {
                throw ValidationException::withMessages(['comprobantes' => ['No se puede dar de baja una NOTA_VENTA en SUNAT.']]);
            }

            if (! in_array($documento->tipo_comprobante, [Venta::FACTURA, Venta::BOLETA, NotaElectronica::NOTA_CREDITO, NotaElectronica::NOTA_DEBITO], true)) {
                throw ValidationException::withMessages(['comprobantes' => ['Tipo de comprobante no permitido para comunicacion de baja.']]);
            }

            if ($this->yaTieneBajaVigente($documento->id)) {
                throw ValidationException::withMessages(['comprobantes' => ['Uno o mas comprobantes ya tienen una comunicacion de baja pendiente, enviada o aceptada.']]);
            }
        }

        return $documentos;
    }

    protected function validarFechaBaja(Collection $documentos, string $fechaBaja): void
    {
        foreach ($documentos as $documento) {
            if ($documento->fecha_emision->toDateString() !== $fechaBaja) {
                throw ValidationException::withMessages(['fecha_baja' => ['La fecha de baja debe coincidir con la fecha de emision de todos los comprobantes.']]);
            }
        }
    }

    protected function actualizarEstadoDesdeTicket(ComunicacionBaja $baja, StatusResult $response): ComunicacionBaja
    {
        $cdrResponse = $response->getCdrResponse();
        $error = $response->getError();
        $codigo = $cdrResponse?->getCode() ?? $response->getCode() ?? $error?->getCode();
        $mensaje = $cdrResponse?->getDescription() ?? $error?->getMessage() ?? 'Respuesta SUNAT recibida.';
        $estado = ComunicacionBaja::ENVIADO;

        if ($response->getCode() === '98') {
            $mensaje = 'La comunicacion de baja sigue en proceso en SUNAT.';
        } elseif (($cdrResponse && $cdrResponse->isAccepted()) || $response->isSuccess()) {
            $estado = ComunicacionBaja::ACEPTADO;
        } elseif ($response->getCode() === '99' || $cdrResponse || $error) {
            $estado = ComunicacionBaja::RECHAZADO;
        }

        if ($response->getCdrZip()) {
            $this->guardarCdr($baja, $response->getCdrZip());
        }

        $baja->update([
            'estado_sunat' => $estado,
            'codigo_respuesta' => $codigo,
            'mensaje_respuesta' => $mensaje,
            'aceptado_at' => $estado === ComunicacionBaja::ACEPTADO ? now() : $baja->aceptado_at,
            'rechazado_at' => $estado === ComunicacionBaja::RECHAZADO ? now() : $baja->rechazado_at,
        ]);

        if ($estado === ComunicacionBaja::ACEPTADO) {
            $this->marcarComprobantesDadosDeBaja($baja->refresh());
        }

        return $this->cargarBaja($baja->refresh());
    }

    protected function marcarComprobantesDadosDeBaja(ComunicacionBaja $baja): void
    {
        $ids = $baja->detalles()->pluck('comprobante_electronico_id');

        ComprobanteElectronico::whereIn('id', $ids)->update([
            'estado_sunat' => ComprobanteElectronico::DADO_DE_BAJA,
            'comunicacion_baja_id' => $baja->id,
            'dado_baja_at' => now(),
        ]);
    }

    protected function yaTieneBajaVigente(int $comprobanteId): bool
    {
        return ComunicacionBajaDetalle::where('comprobante_electronico_id', $comprobanteId)
            ->whereHas('comunicacionBaja', fn ($query) => $query->whereIn('estado_sunat', [
                ComunicacionBaja::PENDIENTE,
                ComunicacionBaja::ENVIADO,
                ComunicacionBaja::ACEPTADO,
            ]))
            ->exists();
    }

    protected function findScoped(int $bajaId, array $scope): ComunicacionBaja
    {
        return ComunicacionBaja::with(['detalles.comprobanteElectronico'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($bajaId);
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
        $ultimo = ComunicacionBaja::where('empresa_id', $empresaId)
            ->where('tienda_id', $tiendaId)
            ->whereDate('fecha_envio', $fechaEnvio)
            ->lockForUpdate()
            ->max('correlativo');

        return ((int) $ultimo) + 1;
    }

    protected function formatearIdentificador(string $fechaEnvio, int $correlativo): string
    {
        return 'RA-'.Carbon::parse($fechaEnvio)->format('Ymd').'-'.str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
    }

    protected function tipoDocumentoSunat(ComprobanteElectronico $documento): string
    {
        return match ($documento->tipo_comprobante) {
            Venta::FACTURA => '01',
            Venta::BOLETA => '03',
            NotaElectronica::NOTA_CREDITO => '07',
            NotaElectronica::NOTA_DEBITO => '08',
            default => throw ValidationException::withMessages(['tipo_comprobante' => ['Documento no permitido para comunicacion de baja.']]),
        };
    }

    protected function guardarXmlFirmado(ComunicacionBaja $baja, string $xml): void
    {
        Storage::disk('local')->put($this->xmlPath($baja), $xml);
        $baja->update(['xml_path' => $this->xmlPath($baja)]);
    }

    protected function guardarCdr(ComunicacionBaja $baja, string $cdr): void
    {
        Storage::disk('local')->put($this->cdrPath($baja), $cdr);
        $baja->update(['cdr_path' => $this->cdrPath($baja)]);
    }

    protected function xmlPath(ComunicacionBaja $baja): string
    {
        return 'private/sunat/bajas/'.$baja->empresa_id.'/'.$baja->fecha_envio->format('Y-m-d').'/xml/'.$baja->identificador.'.xml';
    }

    protected function cdrPath(ComunicacionBaja $baja): string
    {
        return 'private/sunat/bajas/'.$baja->empresa_id.'/'.$baja->fecha_envio->format('Y-m-d').'/cdr/R-'.$baja->identificador.'.zip';
    }

    protected function cargarBaja(ComunicacionBaja $baja): ComunicacionBaja
    {
        return $baja->load(['detalles.comprobanteElectronico'])->loadCount('detalles');
    }
}
