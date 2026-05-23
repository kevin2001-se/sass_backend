<?php

namespace App\Services;

use App\Models\Distrito;
use App\Models\GuiaRemision;
use App\Models\MotivoTraslado;
use App\Models\SerieComprobante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuiaRemisionService
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        return GuiaRemision::query()
            ->with(['cliente', 'createdBy', 'motivoTraslado', 'modalidadTransporte', 'venta', 'comprobante'])
            ->where('tenant_id', $filtros['tenant_id'])
            ->where('empresa_id', $filtros['empresa_id'])
            ->where('tienda_id', $filtros['tienda_id'])
            ->when($filtros['fecha_inicio'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('fecha_emision', '>=', $fecha))
            ->when($filtros['fecha_fin'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('fecha_emision', '<=', $fecha))
            ->when($filtros['estado'] ?? null, fn (Builder $query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['motivo_traslado_codigo'] ?? null, fn (Builder $query, string $codigo) => $query->where('motivo_traslado_codigo', $codigo))
            ->when($filtros['modalidad_transporte'] ?? null, fn (Builder $query, string $modalidad) => $query->where('modalidad_transporte', $modalidad))
            ->when($filtros['numero'] ?? null, function (Builder $query, string $numero) {
                $query->where(function (Builder $subquery) use ($numero) {
                    $subquery->where('numero_completo', 'ILIKE', "%{$numero}%")
                        ->orWhere('numero_guia', 'ILIKE', "%{$numero}%");
                });
            })
            ->when($filtros['destinatario'] ?? null, function (Builder $query, string $destinatario) {
                $query->where(function (Builder $subquery) use ($destinatario) {
                    $subquery->where('destinatario_nombre', 'ILIKE', "%{$destinatario}%")
                        ->orWhere('destinatario_numero_documento', 'ILIKE', "%{$destinatario}%");
                });
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate((int) ($filtros['per_page'] ?? 15));
    }

    public function crear(array $data): GuiaRemision
    {
        return DB::transaction(function () use ($data) {
            $data = $this->resolverUbigeosDesdeDistritos($data);
            $numero = $this->generarNumero($data['tienda_id'], $data['tenant_id'], $data['empresa_id']);
            $motivo = $this->obtenerMotivo($data['motivo_traslado_codigo']);
            $payload = $this->guiaPayload($data, $numero, $motivo->descripcion);

            $guia = GuiaRemision::create($payload);
            $this->crearDetalles($guia, $data['detalles']);

            return $this->obtener($guia->id, $data);
        });
    }

    public function actualizar(GuiaRemision $guia, array $data): GuiaRemision
    {
        $this->validarPerteneceAlContexto($guia, $data);

        if ($guia->estado !== GuiaRemision::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se puede editar una guia en estado BORRADOR.'],
            ]);
        }

        return DB::transaction(function () use ($guia, $data) {
            $data = $this->resolverUbigeosDesdeDistritos($data);
            $motivo = $this->obtenerMotivo($data['motivo_traslado_codigo']);
            $payload = $this->guiaPayload($data, [
                'serie' => $guia->serie,
                'correlativo' => $guia->correlativo,
                'numero_completo' => $guia->numero_completo ?: $guia->numero_guia,
            ], $motivo->descripcion, false);

            $guia->update($payload);
            $guia->detalles()->delete();
            $this->crearDetalles($guia, $data['detalles']);

            return $this->obtener($guia->id, $data);
        });
    }

    public function obtener(int $id, array $scope): GuiaRemision
    {
        return GuiaRemision::query()
            ->with(['cliente', 'detalles.producto', 'createdBy', 'updatedBy', 'motivoTraslado', 'modalidadTransporte', 'venta', 'comprobante'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    public function anular(GuiaRemision $guia, string $motivo, array $scope): GuiaRemision
    {
        $this->validarPerteneceAlContexto($guia, $scope);

        if (! in_array($guia->estado, [GuiaRemision::BORRADOR, GuiaRemision::REGISTRADA], true)) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se puede anular una guia en estado BORRADOR o REGISTRADA.'],
            ]);
        }

        $observacion = trim((string) $guia->observacion);
        $observacion = $observacion === '' ? "Anulada: {$motivo}" : "{$observacion}\nAnulada: {$motivo}";

        $guia->update([
            'estado' => GuiaRemision::ANULADA,
            'observacion' => $observacion,
            'updated_by' => $scope['user_id'] ?? null,
        ]);

        return $this->obtener($guia->id, $scope);
    }

    public function registrar(GuiaRemision $guia, array $scope): GuiaRemision
    {
        $this->validarPerteneceAlContexto($guia, $scope);

        if ($guia->estado !== GuiaRemision::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se puede registrar una guia en estado BORRADOR.'],
            ]);
        }

        if ($guia->fecha_traslado && $guia->fecha_traslado->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'fecha_traslado' => ['La fecha de traslado no puede ser menor a hoy para registrar la guia.'],
            ]);
        }

        if ($guia->detalles()->count() === 0) {
            throw ValidationException::withMessages([
                'detalles' => ['La guia debe tener al menos un detalle.'],
            ]);
        }

        $guia->update([
            'estado' => GuiaRemision::REGISTRADA,
            'updated_by' => $scope['user_id'] ?? null,
        ]);

        return $this->obtener($guia->id, $scope);
    }
    public function generarNumero(int $tiendaId, ?int $tenantId = null, ?int $empresaId = null): array
    {
        $serie = SerieComprobante::query()
            ->where('tipo_comprobante', 'GUIA_REMISION')
            ->where('tienda_id', $tiendaId)
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->when($empresaId, fn (Builder $query) => $query->where('empresa_id', $empresaId))
            ->where('estado', true)
            ->lockForUpdate()
            ->first();

        if (! $serie) {
            throw ValidationException::withMessages([
                'serie' => ['No existe una serie activa para GUIA_REMISION en la tienda activa.'],
            ]);
        }

        $correlativo = $serie->correlativo_actual + 1;
        $serie->update(['correlativo_actual' => $correlativo]);

        return [
            'serie' => $serie->serie,
            'correlativo' => $correlativo,
            'numero_completo' => $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT),
        ];
    }


    protected function resolverUbigeosDesdeDistritos(array $data): array
    {
        foreach (['punto_partida', 'punto_llegada'] as $prefix) {
            $distritoId = $data["{$prefix}_distrito_id"] ?? null;

            if (! $distritoId) {
                continue;
            }

            $ubigeo = Distrito::where('id', $distritoId)->value('ubigeo');

            if ($ubigeo) {
                $data["{$prefix}_ubigeo"] = $ubigeo;
            }
        }

        return $data;
    }
    protected function guiaPayload(array $data, array $numero, string $motivoDescripcion, bool $crear = true): array
    {
        $modalidadPublica = $data['modalidad_transporte'] === '01';

        $payload = [
            'tenant_id' => $data['tenant_id'],
            'empresa_id' => $data['empresa_id'],
            'tienda_id' => $data['tienda_id'],
            'cliente_id' => $data['cliente_id'] ?? null,
            'venta_id' => $data['venta_id'] ?? null,
            'comprobante_id' => $data['comprobante_id'] ?? null,
            'tipo_referencia' => $data['tipo_referencia'] ?? null,
            'referencia_serie' => $data['referencia_serie'] ?? null,
            'referencia_numero' => $data['referencia_numero'] ?? null,
            'serie' => Str::upper($numero['serie']),
            'correlativo' => $numero['correlativo'],
            'numero_completo' => Str::upper($numero['numero_completo']),
            'numero_guia' => Str::upper($numero['numero_completo']),
            'fecha_emision' => $data['fecha_emision'] ?? now(),
            'fecha_traslado' => $data['fecha_traslado'],
            'motivo_traslado_codigo' => $data['motivo_traslado_codigo'],
            'motivo_traslado_descripcion' => $data['motivo_traslado_descripcion'] ?? $motivoDescripcion,
            'modalidad_transporte' => $data['modalidad_transporte'],
            'destinatario_tipo_documento' => $data['destinatario_tipo_documento'],
            'destinatario_numero_documento' => $data['destinatario_numero_documento'],
            'destinatario_nombre' => $data['destinatario_nombre'],
            'punto_partida_ubigeo' => $data['punto_partida_ubigeo'],
            'punto_partida_direccion' => $data['punto_partida_direccion'],
            'punto_llegada_ubigeo' => $data['punto_llegada_ubigeo'],
            'punto_llegada_direccion' => $data['punto_llegada_direccion'],
            'transportista_tipo_documento' => $modalidadPublica ? 'RUC' : null,
            'transportista_numero_documento' => $modalidadPublica ? ($data['transportista_ruc'] ?? null) : null,
            'transportista_ruc' => $modalidadPublica ? ($data['transportista_ruc'] ?? null) : null,
            'transportista_razon_social' => $modalidadPublica ? ($data['transportista_razon_social'] ?? null) : null,
            'conductor_tipo_documento' => $modalidadPublica ? null : ($data['conductor_tipo_documento'] ?? null),
            'conductor_numero_documento' => $modalidadPublica ? null : ($data['conductor_numero_documento'] ?? null),
            'conductor_nombre' => $modalidadPublica ? null : ($data['conductor_nombre'] ?? null),
            'conductor_licencia' => $modalidadPublica ? null : ($data['conductor_licencia'] ?? null),
            'vehiculo_placa' => $modalidadPublica ? null : ($data['vehiculo_placa'] ?? null),
            'peso_total' => $data['peso_total'],
            'unidad_peso' => $data['unidad_peso'] ?? 'KGM',
            'numero_bultos' => $data['numero_bultos'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'estado' => $data['estado'] ?? GuiaRemision::BORRADOR,
            'created_by' => $crear ? ($data['user_id'] ?? null) : null,
            'updated_by' => $crear ? null : ($data['user_id'] ?? null),
        ];

        if (! $crear) {
            unset($payload['created_by']);
        }

        return $payload;
    }

    protected function normalizarData(array $data): array
    {
        $data['modalidad_transporte'] = match (strtoupper(trim((string) ($data['modalidad_transporte'] ?? '')))) {
            'PUBLICO' => '01',
            'PRIVADO' => '02',
            default => strtoupper(trim((string) ($data['modalidad_transporte'] ?? ''))),
        };

        foreach ([
            'motivo_traslado_codigo',
            'destinatario_tipo_documento',
            'destinatario_numero_documento',
            'punto_partida_ubigeo',
            'punto_llegada_ubigeo',
            'conductor_tipo_documento',
            'conductor_numero_documento',
            'transportista_ruc',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        foreach ([
            'motivo_traslado_descripcion',
            'destinatario_nombre',
            'punto_partida_direccion',
            'punto_llegada_direccion',
            'conductor_nombre',
            'transportista_razon_social',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = Str::upper(trim(preg_replace('/\s+/', ' ', (string) $data[$field])));
            }
        }

        if (array_key_exists('vehiculo_placa', $data)) {
            $data['vehiculo_placa'] = Str::upper(str_replace(' ', '', trim((string) $data['vehiculo_placa'])));
        }

        if (array_key_exists('conductor_licencia', $data)) {
            $data['conductor_licencia'] = Str::upper(trim((string) $data['conductor_licencia']));
        }

        $data['unidad_peso'] = Str::upper(trim((string) ($data['unidad_peso'] ?? 'KGM')));
        $data['estado'] = Str::upper(trim((string) ($data['estado'] ?? GuiaRemision::BORRADOR)));

        $data['detalles'] = collect($data['detalles'] ?? [])->map(function (array $detalle) {
            $detalle['descripcion'] = Str::upper(trim(preg_replace('/\s+/', ' ', (string) ($detalle['descripcion'] ?? ''))));
            $detalle['unidad_medida'] = Str::upper(trim((string) ($detalle['unidad_medida'] ?? '')));

            return $detalle;
        })->all();

        return $data;
    }

    protected function validarNegocio(array $data): void
    {
        $mismaDireccion = Str::upper(trim(preg_replace('/\s+/', ' ', (string) $data['punto_partida_direccion']))) ===
            Str::upper(trim(preg_replace('/\s+/', ' ', (string) $data['punto_llegada_direccion'])));

        if (($data['punto_partida_ubigeo'] ?? null) === ($data['punto_llegada_ubigeo'] ?? null) && $mismaDireccion) {
            throw ValidationException::withMessages([
                'punto_llegada_direccion' => ['El punto de partida y llegada no pueden ser exactamente iguales.'],
            ]);
        }

        foreach ($data['detalles'] ?? [] as $index => $detalle) {
            if (empty($detalle['producto_id'])) {
                continue;
            }

            $existe = \App\Models\Producto::where('tenant_id', $data['tenant_id'])
                ->where('empresa_id', $data['empresa_id'])
                ->where('id', $detalle['producto_id'])
                ->exists();

            if (! $existe) {
                throw ValidationException::withMessages([
                    "detalles.{$index}.producto_id" => ['El producto no pertenece a la empresa actual.'],
                ]);
            }
        }
    }

    protected function crearDetalles(GuiaRemision $guia, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $guia->detalles()->create([
                'tenant_id' => $guia->tenant_id,
                'empresa_id' => $guia->empresa_id,
                'producto_id' => $detalle['producto_id'] ?? null,
                'descripcion' => $detalle['descripcion'],
                'unidad_medida' => $detalle['unidad_medida'],
                'cantidad' => $detalle['cantidad'],
                'peso' => $detalle['peso'] ?? null,
            ]);
        }
    }

    protected function obtenerMotivo(string $codigo): MotivoTraslado
    {
        $motivo = MotivoTraslado::where('codigo', $codigo)->where('estado', true)->first();

        if (! $motivo) {
            throw ValidationException::withMessages([
                'motivo_traslado_codigo' => ['El motivo de traslado no esta activo.'],
            ]);
        }

        return $motivo;
    }

    protected function validarPerteneceAlContexto(GuiaRemision $guia, array $scope): void
    {
        if (
            (int) $guia->tenant_id !== (int) $scope['tenant_id'] ||
            (int) $guia->empresa_id !== (int) $scope['empresa_id'] ||
            (int) $guia->tienda_id !== (int) $scope['tienda_id']
        ) {
            throw ValidationException::withMessages([
                'guia' => ['La guia no pertenece al contexto actual.'],
            ]);
        }
    }
}



