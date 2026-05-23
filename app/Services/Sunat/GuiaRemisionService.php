<?php

namespace App\Services\Sunat;

use App\Models\Compra;
use App\Models\ComprobanteElectronico;
use App\Models\GuiaRemision;
use App\Models\GuiaRemisionDetalle;
use App\Models\GuiaRemisionDocumentoRelacionado;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\SerieComprobante;
use App\Models\SunatConfiguracion;
use App\Models\Venta;
use Greenter\Model\Response\BaseResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GuiaRemisionService
{
    public function __construct(
        private readonly SunatClientFactory $clientFactory,
        private readonly SunatGuiaRemisionBuilder $builder
    ) {
    }

    public function crearGuia(array $data): GuiaRemision
    {
        return DB::transaction(function () use ($data) {
            $this->configuracionActiva($data['tenant_id'], $data['empresa_id']);
            [$serie, $correlativo, $numero] = $this->generarNumeroGuia($data['tenant_id'], $data['empresa_id'], $data['tienda_id']);

            $guia = GuiaRemision::create($this->payloadGuia($data, $serie, $correlativo, $numero));
            $this->crearDetalles($guia, $data['detalles'] ?? []);
            $this->crearDocumentosRelacionados($guia, $data['documentos_relacionados'] ?? []);
            $this->crearComprobanteElectronicoGuia($guia);

            return $this->cargarGuia($guia);
        });
    }

    public function crearDesdeVenta(int $ventaId, array $data): GuiaRemision
    {
        return DB::transaction(function () use ($ventaId, $data) {
            $venta = Venta::with(['cliente', 'detalles.producto', 'comprobanteElectronico'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->findOrFail($ventaId);

            if ($venta->estado !== Venta::REGISTRADA) {
                throw ValidationException::withMessages(['venta' => ['No se puede generar guia desde una venta anulada.']]);
            }

            if ($venta->comprobanteElectronico && $venta->comprobanteElectronico->estado_sunat === ComprobanteElectronico::DADO_DE_BAJA) {
                throw ValidationException::withMessages(['venta' => ['No se puede generar guia desde un comprobante dado de baja.']]);
            }

            $data['venta_id'] = $venta->id;
            $data['cliente_id'] = $venta->cliente_id;
            $data['detalles'] = $venta->detalles->map(fn ($detalle) => [
                'producto_id' => $detalle->producto_id,
                'producto_presentacion_id' => $detalle->producto_presentacion_id,
                'descripcion' => $detalle->descripcion,
                'cantidad' => $detalle->cantidad_presentacion,
                'unidad_medida' => 'NIU',
                'codigo_producto' => $detalle->producto?->codigo_interno,
            ])->all();
            $data['documentos_relacionados'] = $venta->comprobanteElectronico ? [[
                'tipo_documento' => $venta->tipo_comprobante,
                'serie' => $venta->serie,
                'numero' => str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
                'comprobante_electronico_id' => $venta->comprobanteElectronico->id,
                'venta_id' => $venta->id,
            ]] : [];

            return $this->crearGuia($data);
        });
    }

    public function crearDesdeCompra(int $compraId, array $data): GuiaRemision
    {
        return DB::transaction(function () use ($compraId, $data) {
            $compra = Compra::with(['proveedor', 'detalles.producto'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('tienda_id', $data['tienda_id'])
                ->findOrFail($compraId);

            if ($compra->estado !== Compra::REGISTRADA) {
                throw ValidationException::withMessages(['compra' => ['No se puede generar guia desde una compra anulada.']]);
            }

            $data['compra_id'] = $compra->id;
            $data['proveedor_id'] = $compra->proveedor_id;
            $data['detalles'] = $compra->detalles->map(fn ($detalle) => [
                'producto_id' => $detalle->producto_id,
                'producto_presentacion_id' => $detalle->producto_presentacion_id,
                'descripcion' => $detalle->descripcion,
                'cantidad' => $detalle->cantidad_presentacion,
                'unidad_medida' => 'NIU',
                'codigo_producto' => $detalle->producto?->codigo_interno,
            ])->all();
            $data['documentos_relacionados'] = [[
                'tipo_documento' => $compra->tipo_comprobante,
                'serie' => $compra->serie,
                'numero' => $compra->numero,
                'compra_id' => $compra->id,
            ]];

            return $this->crearGuia($data);
        });
    }

    public function enviarGuiaConGreenter(int $guiaId, array $scope): GuiaRemision
    {
        $guia = $this->findScoped($guiaId, $scope);
        $comprobante = $guia->comprobanteElectronico;

        if ($guia->estado === GuiaRemision::ANULADA) {
            throw ValidationException::withMessages(['guia' => ['No se puede enviar una guia anulada.']]);
        }

        if ($comprobante?->estado_sunat === ComprobanteElectronico::ACEPTADO) {
            throw ValidationException::withMessages(['guia' => ['La guia ya fue aceptada por SUNAT.']]);
        }

        try {
            $configuracion = $this->configuracionActiva($guia->tenant_id, $guia->empresa_id);
            $see = $this->clientFactory->make($configuracion);
            $despatch = $this->builder->buildFromGuia($guia);
            $xml = $see->getXmlSigned($despatch);

            if (! $xml) {
                throw new RuntimeException('Greenter no pudo generar el XML firmado de la guia.');
            }

            $this->guardarXmlFirmado($comprobante, $xml);
            $response = $see->sendXml($despatch::class, $despatch->getName(), $xml);

            if (! $response) {
                throw new RuntimeException('SUNAT no devolvio respuesta para la guia.');
            }

            return $this->actualizarEstadoSunat($guia, $comprobante->refresh(), $response);
        } catch (Throwable $e) {
            $comprobante?->increment('intentos_envio');
            $comprobante?->update([
                'estado_sunat' => ComprobanteElectronico::ERROR,
                'mensaje_respuesta' => $e->getMessage(),
                'enviado_at' => now(),
                'observacion' => trim(($comprobante->observacion ? $comprobante->observacion.' | ' : '').'ERROR: '.$e->getMessage()),
            ]);

            throw ValidationException::withMessages(['sunat' => ['No se pudo enviar la guia a SUNAT. '.$e->getMessage()]]);
        }
    }

    public function reenviarGuia(int $guiaId, array $scope): GuiaRemision
    {
        return $this->enviarGuiaConGreenter($guiaId, $scope);
    }

    public function anularGuia(int $guiaId, string $motivo, array $scope): GuiaRemision
    {
        return DB::transaction(function () use ($guiaId, $motivo, $scope) {
            $guia = $this->findScoped($guiaId, $scope);

            if ($guia->estado === GuiaRemision::ANULADA) {
                throw ValidationException::withMessages(['guia' => ['La guia ya esta anulada.']]);
            }

            $guia->update([
                'estado' => GuiaRemision::ANULADA,
                'observacion' => trim(($guia->observacion ? $guia->observacion.' | ' : '').'ANULADA: '.$motivo),
            ]);

            return $this->cargarGuia($guia->refresh());
        });
    }

    public function generarNumeroGuia(int $tenantId, int $empresaId, int $tiendaId): array
    {
        $serie = SerieComprobante::where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('tienda_id', $tiendaId)
            ->where('tipo_comprobante', 'GUIA_REMISION')
            ->where('estado', true)
            ->lockForUpdate()
            ->first();

        if (! $serie) {
            throw ValidationException::withMessages(['serie' => ['No existe serie activa para GUIA_REMISION en la tienda activa.']]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [$serie->serie, $correlativo, $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT)];
    }

    protected function payloadGuia(array $data, string $serie, int $correlativo, string $numero): array
    {
        return [
            'tenant_id' => $data['tenant_id'],
            'empresa_id' => $data['empresa_id'],
            'tienda_id' => $data['tienda_id'],
            'venta_id' => $data['venta_id'] ?? null,
            'compra_id' => $data['compra_id'] ?? null,
            'cliente_id' => $data['cliente_id'] ?? null,
            'proveedor_id' => $data['proveedor_id'] ?? null,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero_guia' => $numero,
            'fecha_emision' => now(),
            'fecha_traslado' => $data['fecha_traslado'],
            'motivo_traslado_codigo' => $data['motivo_traslado_codigo'],
            'motivo_traslado_descripcion' => $data['motivo_traslado_descripcion'],
            'modalidad_transporte' => $data['modalidad_transporte'],
            'peso_total' => $data['peso_total'],
            'unidad_peso' => $data['unidad_peso'],
            'numero_bultos' => $data['numero_bultos'] ?? null,
            'punto_partida_ubigeo' => $data['punto_partida_ubigeo'],
            'punto_partida_direccion' => $data['punto_partida_direccion'],
            'punto_llegada_ubigeo' => $data['punto_llegada_ubigeo'],
            'punto_llegada_direccion' => $data['punto_llegada_direccion'],
            'transportista_tipo_documento' => $data['transportista_tipo_documento'] ?? null,
            'transportista_numero_documento' => $data['transportista_numero_documento'] ?? null,
            'transportista_razon_social' => $data['transportista_razon_social'] ?? null,
            'conductor_tipo_documento' => $data['conductor_tipo_documento'] ?? null,
            'conductor_numero_documento' => $data['conductor_numero_documento'] ?? null,
            'conductor_nombre' => $data['conductor_nombre'] ?? null,
            'conductor_licencia' => $data['conductor_licencia'] ?? null,
            'vehiculo_placa' => $data['vehiculo_placa'] ?? null,
            'estado' => GuiaRemision::REGISTRADA,
            'observacion' => $data['observacion'] ?? null,
        ];
    }

    protected function crearDetalles(GuiaRemision $guia, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $producto = Producto::where('tenant_id', $guia->tenant_id)->where('empresa_id', $guia->empresa_id)->findOrFail($detalle['producto_id']);

            if (! empty($detalle['producto_presentacion_id'])) {
                ProductoPresentacion::where('tenant_id', $guia->tenant_id)
                    ->where('empresa_id', $guia->empresa_id)
                    ->where('producto_id', $producto->id)
                    ->findOrFail($detalle['producto_presentacion_id']);
            }

            GuiaRemisionDetalle::create([
                'tenant_id' => $guia->tenant_id,
                'empresa_id' => $guia->empresa_id,
                'guia_remision_id' => $guia->id,
                'producto_id' => $producto->id,
                'producto_presentacion_id' => $detalle['producto_presentacion_id'] ?? null,
                'descripcion' => $detalle['descripcion'],
                'cantidad' => $detalle['cantidad'],
                'unidad_medida' => $detalle['unidad_medida'],
                'codigo_producto' => $detalle['codigo_producto'] ?? $producto->codigo_interno,
            ]);
        }
    }

    protected function crearDocumentosRelacionados(GuiaRemision $guia, array $documentos): void
    {
        foreach ($documentos as $documento) {
            GuiaRemisionDocumentoRelacionado::create([
                'tenant_id' => $guia->tenant_id,
                'empresa_id' => $guia->empresa_id,
                'guia_remision_id' => $guia->id,
                'tipo_documento' => $documento['tipo_documento'],
                'serie' => $documento['serie'],
                'numero' => $documento['numero'],
                'comprobante_electronico_id' => $documento['comprobante_electronico_id'] ?? null,
                'venta_id' => $documento['venta_id'] ?? null,
                'compra_id' => $documento['compra_id'] ?? null,
            ]);
        }
    }

    protected function crearComprobanteElectronicoGuia(GuiaRemision $guia): ComprobanteElectronico
    {
        return ComprobanteElectronico::create([
            'tenant_id' => $guia->tenant_id,
            'empresa_id' => $guia->empresa_id,
            'tienda_id' => $guia->tienda_id,
            'guia_remision_id' => $guia->id,
            'documento_origen_tipo' => 'GUIA_REMISION',
            'documento_origen_id' => $guia->id,
            'tipo_comprobante' => 'GUIA_REMISION',
            'serie' => $guia->serie,
            'correlativo' => $guia->correlativo,
            'numero_comprobante' => $guia->numero_guia,
            'fecha_emision' => $guia->fecha_emision,
            'moneda' => 'PEN',
            'estado_sunat' => ComprobanteElectronico::PENDIENTE,
        ]);
    }

    protected function actualizarEstadoSunat(GuiaRemision $guia, ComprobanteElectronico $comprobante, BaseResult $response): GuiaRemision
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

        return $this->cargarGuia($guia->refresh());
    }

    protected function configuracionActiva(int $tenantId, int $empresaId): SunatConfiguracion
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $tenantId)->where('empresa_id', $empresaId)->where('estado', true)->first();

        if (! $configuracion) {
            throw ValidationException::withMessages(['sunat_configuracion' => ['No existe configuracion SUNAT activa para esta empresa.']]);
        }

        return $configuracion;
    }

    protected function findScoped(int $guiaId, array $scope): GuiaRemision
    {
        return GuiaRemision::with(['detalles', 'documentosRelacionados', 'comprobanteElectronico', 'empresa.sunatConfiguraciones', 'cliente', 'proveedor'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($guiaId);
    }

    protected function guardarXmlFirmado(ComprobanteElectronico $comprobante, string $xml): void
    {
        Storage::disk('local')->put($this->xmlPath($comprobante), $xml);
        $comprobante->update([
            'xml_path' => $this->xmlPath($comprobante),
            'hash' => hash('sha256', $xml),
        ]);
    }

    protected function guardarCdr(ComprobanteElectronico $comprobante, string $cdr): void
    {
        Storage::disk('local')->put($this->cdrPath($comprobante), $cdr);
        $comprobante->update(['cdr_path' => $this->cdrPath($comprobante)]);
    }

    protected function xmlPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/guias/'.$comprobante->empresa_id.'/'.$comprobante->fecha_emision->format('Y-m-d').'/xml/'.$comprobante->numero_comprobante.'.xml';
    }

    protected function cdrPath(ComprobanteElectronico $comprobante): string
    {
        return 'private/sunat/guias/'.$comprobante->empresa_id.'/'.$comprobante->fecha_emision->format('Y-m-d').'/cdr/R-'.$comprobante->numero_comprobante.'.zip';
    }

    protected function cargarGuia(GuiaRemision $guia): GuiaRemision
    {
        return $guia->load(['detalles', 'documentosRelacionados', 'comprobanteElectronico']);
    }
}
