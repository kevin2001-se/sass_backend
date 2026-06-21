<?php

namespace App\Services;

use App\Models\Parametro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParametroService
{
    public function get(string $clave, mixed $default = null): mixed
    {
        $parametro = $this->queryActual()
            ->where('clave', $clave)
            ->where('estado', true)
            ->first();

        if (! $parametro) {
            return $default;
        }

        return $this->castValue($parametro->valor, $parametro->tipo, $default);
    }

    public function set(string $clave, mixed $valor): Parametro
    {
        [$tenantId, $empresaId] = $this->scope();
        $parametro = Parametro::where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->first();

        if (! $parametro) {
            $parametro = new \parametro([
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'clave' => $clave,
                'tipo' => $this->inferType($valor),
                'grupo' => 'sistema',
                'estado' => true,
            ]);
        }

        $parametro->valor = $this->normalizeValue($valor, $parametro->tipo);
        $parametro->save();

        return $parametro;
    }

    public function exists(string $clave): bool
    {
        return $this->queryActual()
            ->where('clave', $clave)
            ->where('estado', true)
            ->exists();
    }

    public function listarAgrupados(): Collection
    {
        return $this->queryActual()
            ->where('estado', true)
            ->orderBy('grupo')
            ->orderBy('clave')
            ->get()
            ->groupBy('grupo');
    }

    public function listarPorGrupo(string $grupo): Collection
    {
        if (! in_array($grupo, Parametro::GRUPOS, true)) {
            throw ValidationException::withMessages(['grupo' => ['Grupo de parametro invalido.']]);
        }

        return $this->queryActual()
            ->where('grupo', $grupo)
            ->where('estado', true)
            ->orderBy('clave')
            ->get();
    }

    public function getByGroup(string $grupo): array
    {
        if (! in_array($grupo, Parametro::GRUPOS, true)) {
            throw ValidationException::withMessages(['grupo' => ['Grupo de parametro invalido.']]);
        }

        return $this->queryActual()
            ->where('grupo', $grupo)
            ->where('estado', true)
            ->orderBy('clave')
            ->get()
            ->mapWithKeys(fn (Parametro $parametro) => [
                $parametro->clave => $this->castValue($parametro->valor, $parametro->tipo),
            ])
            ->all();
    }

    public function actualizarMultiples(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            $actualizados = collect();

            foreach ($items as $index => $item) {
                $clave = $item['clave'] ?? null;
                $field = "parametros.$index.valor";
                $parametro = $this->queryActual()
                    ->where('clave', $clave)
                    ->where('estado', true)
                    ->lockForUpdate()
                    ->first();

                if (! $parametro) {
                    throw ValidationException::withMessages([
                        "parametros.$index.clave" => ['El parametro indicado no existe para la empresa actual.'],
                    ]);
                }

                $valor = $item['valor'] ?? null;
                $this->validarValor($parametro, $valor, $field);

                $actualizados->push($this->set($parametro->clave, $valor)->fresh());
            }

            return $actualizados;
        });
    }

    public function castValue(?string $valor, string $tipo, mixed $default = null): mixed
    {
        if ($valor === null) {
            return $default;
        }

        return match ($tipo) {
            Parametro::TIPO_BOOLEAN => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            Parametro::TIPO_INTEGER => (int) $valor,
            Parametro::TIPO_DECIMAL => (float) $valor,
            Parametro::TIPO_JSON => json_decode($valor, true) ?? [],
            default => $valor,
        };
    }

    protected function normalizeValue(mixed $valor, string $tipo): ?string
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            Parametro::TIPO_BOOLEAN => $valor ? 'true' : 'false',
            Parametro::TIPO_INTEGER => (string) ((int) $valor),
            Parametro::TIPO_DECIMAL => (string) ((float) $valor),
            Parametro::TIPO_JSON => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $valor,
        };
    }

    protected function validarValor(Parametro $parametro, mixed $valor, string $field): void
    {
        if ($valor === null && $parametro->tipo !== Parametro::TIPO_STRING) {
            throw ValidationException::withMessages([$field => ['El valor es requerido para este tipo de parametro.']]);
        }

        if ($valor === null) {
            return;
        }

        $isValid = match ($parametro->tipo) {
            Parametro::TIPO_BOOLEAN => is_bool($valor) || in_array($valor, [0, 1, '0', '1', 'true', 'false'], true),
            Parametro::TIPO_INTEGER => filter_var($valor, FILTER_VALIDATE_INT) !== false,
            Parametro::TIPO_DECIMAL => is_numeric($valor),
            Parametro::TIPO_JSON => is_array($valor),
            Parametro::TIPO_STRING => is_scalar($valor),
            default => false,
        };

        if (! $isValid) {
            throw ValidationException::withMessages([$field => ["El valor no corresponde al tipo {$parametro->tipo}."]]);
        }
    }

    protected function inferType(mixed $valor): string
    {
        return match (true) {
            is_bool($valor) => Parametro::TIPO_BOOLEAN,
            is_int($valor) => Parametro::TIPO_INTEGER,
            is_float($valor) => Parametro::TIPO_DECIMAL,
            is_array($valor) => Parametro::TIPO_JSON,
            default => Parametro::TIPO_STRING,
        };
    }

    protected function queryActual()
    {
        [$tenantId, $empresaId] = $this->scope();

        return Parametro::query()
            ->where('tenant_id', $tenantId)
            ->where('empresa_id', $empresaId);
    }

    protected function scope(): array
    {
        $request = request();
        $tenant = $request?->attributes->get('tenant');
        $empresa = $request?->attributes->get('empresa');
        $user = Auth::user();

        $tenantId = $tenant?->id ?? $user?->tenant_id;
        $empresaId = $empresa?->id ?? $user?->empresa_id;

        if (! $tenantId || ! $empresaId) {
            throw ValidationException::withMessages(['parametros' => ['No se pudo resolver tenant y empresa para parametros.']]);
        }

        return [(int) $tenantId, (int) $empresaId];
    }
}