<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ComprobanteElectronico;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuiaRemisionVentaService
{
    public function __construct(private readonly GuiaRemisionService $guiaService)
    {
    }

    public function crearDesdeVenta(Venta $venta, array $data)
    {
        return DB::transaction(function () use ($venta, $data) {
            $venta = $this->obtenerVentaValida($venta->id, $data);
            $cliente = $this->obtenerClienteValido($venta);
            $tienda = $venta->tienda;
            $comprobante = $venta->comprobanteElectronico;

            if (blank($tienda?->ubigeo) || blank($tienda?->direccion)) {
                throw ValidationException::withMessages([
                    'tienda' => ['Configure ubigeo y direccion de la tienda activa para usarla como punto de partida.'],
                ]);
            }

            $payload = array_merge($data, [
                'venta_id' => $venta->id,
                'comprobante_id' => $comprobante?->id,
                'tipo_referencia' => $comprobante?->tipo_comprobante ?: $venta->tipo_comprobante,
                'referencia_serie' => $comprobante?->serie ?: $venta->serie,
                'referencia_numero' => $this->referenciaNumero($comprobante, $venta),
                'cliente_id' => $cliente->id,
                'destinatario_tipo_documento' => $cliente->tipo_documento,
                'destinatario_numero_documento' => $this->clienteNumeroDocumento($cliente),
                'destinatario_nombre' => $this->clienteNombre($cliente),
                'punto_partida_ubigeo' => $tienda->ubigeo,
                'punto_partida_direccion' => $tienda->direccion,
                'detalles' => $this->detallesDesdeVenta($venta),
            ]);

            return $this->guiaService->crear($payload);
        });
    }

    public function dataDesdeVenta(Venta $venta, array $scope): array
    {
        $venta = $this->obtenerVentaValida($venta->id, $scope);
        $cliente = $venta->cliente;
        $comprobante = $venta->comprobanteElectronico;

        return [
            'venta' => [
                'id' => $venta->id,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'numero' => $venta->numero_comprobante,
                'estado' => $venta->estado,
                'fecha_emision' => $venta->fecha_emision?->toDateTimeString(),
            ],
            'cliente' => $cliente ? [
                'id' => $cliente->id,
                'tipo_documento' => $cliente->tipo_documento,
                'numero_documento' => $this->clienteNumeroDocumento($cliente),
                'nombre' => $this->clienteNombre($cliente),
                'direccion' => $cliente->direccion,
            ] : null,
            'comprobante' => $comprobante ? [
                'id' => $comprobante->id,
                'tipo' => $comprobante->tipo_comprobante,
                'numero' => $comprobante->numero_comprobante,
                'estado_sunat' => $comprobante->estado_sunat,
            ] : [
                'id' => null,
                'tipo' => $venta->tipo_comprobante,
                'numero' => $venta->numero_comprobante,
                'estado_sunat' => null,
            ],
            'direccion_sugerida' => $cliente?->direccion,
            'punto_partida' => [
                'tienda_id' => $venta->tienda?->id,
                'tienda' => $venta->tienda?->nombre,
                'ubigeo' => $venta->tienda?->ubigeo,
                'direccion' => $venta->tienda?->direccion,
            ],
            'productos' => collect($this->detallesDesdeVenta($venta))->map(fn (array $detalle) => [
                'producto_id' => $detalle['producto_id'],
                'descripcion' => $detalle['descripcion'],
                'unidad_medida' => $detalle['unidad_medida'],
                'cantidad' => $detalle['cantidad'],
            ])->values(),
        ];
    }

    protected function obtenerVentaValida(int $ventaId, array $scope): Venta
    {
        $venta = Venta::with([
                'cliente',
                'tienda',
                'detalles.producto',
                'detalles.presentacion.unidadMedida',
                'comprobanteElectronico',
            ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($ventaId);

        if ($venta->estado === Venta::ANULADA) {
            throw ValidationException::withMessages([
                'venta' => ['No se puede generar guia desde una venta anulada.'],
            ]);
        }

        if ($venta->detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'venta' => ['La venta no tiene detalles para generar guia.'],
            ]);
        }

        return $venta;
    }

    protected function obtenerClienteValido(Venta $venta): Cliente
    {
        if (! $venta->cliente) {
            throw ValidationException::withMessages([
                'cliente' => ['La venta no tiene cliente asociado para generar guia de remision.'],
            ]);
        }

        if (! in_array($venta->cliente->tipo_documento, ['DNI', 'RUC', 'CE', 'PASAPORTE', 'SIN_DOCUMENTO'], true)) {
            throw ValidationException::withMessages([
                'cliente' => ['El tipo de documento del cliente no es valido para guia de remision.'],
            ]);
        }

        return $venta->cliente;
    }

    protected function detallesDesdeVenta(Venta $venta): array
    {
        return $venta->detalles->map(function ($detalle) {
            return [
                'producto_id' => $detalle->producto_id,
                'descripcion' => $detalle->descripcion ?: $detalle->producto?->nombre,
                'unidad_medida' => $this->unidadSunat($detalle->presentacion?->unidadMedida?->abreviatura),
                'cantidad' => $detalle->cantidad_presentacion,
                'peso' => null,
            ];
        })->values()->all();
    }

    protected function unidadSunat(?string $abreviatura): string
    {
        return match (strtoupper((string) $abreviatura)) {
            'CAJ', 'CAJA', 'BX' => 'BX',
            'KGM', 'KG' => 'KGM',
            'GRM', 'GR', 'G' => 'GRM',
            'LTR', 'LT', 'L' => 'LTR',
            default => 'NIU',
        };
    }

    protected function clienteNombre(Cliente $cliente): string
    {
        return $cliente->tipo_documento === Cliente::RUC
            ? (string) $cliente->razon_social
            : (string) ($cliente->nombres ?: $cliente->razon_social);
    }

    protected function clienteNumeroDocumento(Cliente $cliente): string
    {
        if ($cliente->tipo_documento === Cliente::SIN_DOCUMENTO) {
            return $cliente->numero_documento ?: '00000000';
        }

        return (string) $cliente->numero_documento;
    }

    protected function referenciaNumero(?ComprobanteElectronico $comprobante, Venta $venta): string
    {
        $correlativo = $comprobante?->correlativo ?: $venta->correlativo;

        return str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);
    }
}