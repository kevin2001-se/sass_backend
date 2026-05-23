<?php

namespace App\Services\Sunat;

use App\Models\SunatConfiguracion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SunatConfiguracionService
{
    public function crear(array $data): SunatConfiguracion
    {
        return DB::transaction(function () use ($data) {
            $data['estado'] = $data['estado'] ?? true;

            if ($data['estado']) {
                $this->validarSinConfiguracionActiva($data['empresa_id']);
            }

            $certificado = $data['certificado'] ?? null;
            unset($data['certificado']);

            $data = $this->aplicarDefaultsGre($data);
            $data['clave_sol'] = Crypt::encryptString($data['clave_sol']);
            $data = $this->cifrarSecretosGre($data, false);

            if (! empty($data['certificado_password'])) {
                $data['certificado_password'] = Crypt::encryptString($data['certificado_password']);
            }

            if ($certificado instanceof UploadedFile) {
                $data['certificado_path'] = $this->guardarCertificado($certificado, $data['empresa_id']);
            }

            return SunatConfiguracion::create($data);
        });
    }

    public function actualizar(SunatConfiguracion $configuracion, array $data): SunatConfiguracion
    {
        return DB::transaction(function () use ($configuracion, $data) {
            if (($data['estado'] ?? $configuracion->estado) && ! $configuracion->estado) {
                $this->validarSinConfiguracionActiva($configuracion->empresa_id, $configuracion->id);
            }

            $certificado = $data['certificado'] ?? null;
            unset($data['certificado']);

            $data = $this->aplicarDefaultsGre($data, $configuracion);

            if (array_key_exists('clave_sol', $data)) {
                if (blank($data['clave_sol'])) {
                    unset($data['clave_sol']);
                } else {
                    $data['clave_sol'] = Crypt::encryptString($data['clave_sol']);
                }
            }

            if (array_key_exists('certificado_password', $data)) {
                if (blank($data['certificado_password'])) {
                    unset($data['certificado_password']);
                } else {
                    $data['certificado_password'] = Crypt::encryptString($data['certificado_password']);
                }
            }

            $data = $this->cifrarSecretosGre($data, true);

            if ($certificado instanceof UploadedFile) {
                if ($configuracion->certificado_path && Storage::disk('local')->exists($configuracion->certificado_path)) {
                    Storage::disk('local')->delete($configuracion->certificado_path);
                }

                $data['certificado_path'] = $this->guardarCertificado($certificado, $configuracion->empresa_id);
            }

            $configuracion->update($data);

            return $configuracion->refresh();
        });
    }

    public function desactivar(SunatConfiguracion $configuracion): SunatConfiguracion
    {
        return DB::transaction(function () use ($configuracion) {
            $configuracion->update(['estado' => false]);

            return $configuracion->refresh();
        });
    }

    public function guardarCertificado(UploadedFile $file, int $empresaId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pfx');

        return $file->storeAs(
            "private/sunat/certificados/{$empresaId}",
            "certificado.{$extension}",
            'local'
        );
    }

    public function obtenerCredencialesDesencriptadas(SunatConfiguracion $configuracion): array
    {
        return [
            'ruc' => $configuracion->ruc,
            'usuario_sol' => $configuracion->usuario_sol,
            'clave_sol' => $configuracion->claveSolDesencriptada(),
            'certificado_path' => $configuracion->certificado_path,
            'certificado_password' => $configuracion->certificadoPasswordDesencriptada(),
        ];
    }

    public function obtenerCredencialesGreDesencriptadas(SunatConfiguracion $configuracion): array
    {
        return [
            'ruc' => $configuracion->ruc,
            'client_id' => $configuracion->gre_client_id,
            'client_secret' => $configuracion->greClientSecretDesencriptado(),
            'usuario_sol' => $configuracion->gre_usuario_sol,
            'clave_sol' => $configuracion->greClaveSolDesencriptada(),
            'scope' => $this->greScope($configuracion),
            'token_url' => $this->greTokenUrl($configuracion),
            'api_url' => $this->greApiUrl($configuracion),
        ];
    }

    public function validarGre(SunatConfiguracion $configuracion): void
    {
        $errors = [];

        if (! $configuracion->gre_modo_envio) {
            $errors['gre_modo_envio'] = ['GRE no esta habilitado para esta empresa.'];
        }
        if (blank($configuracion->ambiente)) {
            $errors['ambiente'] = ['Falta ambiente SUNAT.'];
        }
        if (blank($configuracion->gre_client_id)) {
            $errors['gre_client_id'] = ['Falta GRE Client ID.'];
        }
        if (blank($configuracion->gre_client_secret)) {
            $errors['gre_client_secret'] = ['Falta GRE Client Secret.'];
        }
        if (blank($configuracion->gre_usuario_sol)) {
            $errors['gre_usuario_sol'] = ['Falta usuario SOL GRE.'];
        }
        if (blank($configuracion->gre_clave_sol)) {
            $errors['gre_clave_sol'] = ['Falta clave SOL GRE.'];
        }
        if (blank($this->greTokenUrl($configuracion))) {
            $errors['gre_token_url'] = ['Falta endpoint token GRE.'];
        }
        if (blank($this->greApiUrl($configuracion))) {
            $errors['gre_api_url'] = ['Falta endpoint API GRE.'];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function greScope(SunatConfiguracion $configuracion): string
    {
        return $configuracion->gre_scope ?: (string) config('sunat.gre.'.$this->ambienteKey($configuracion).'.scope');
    }

    public function greTokenUrl(SunatConfiguracion $configuracion): string
    {
        return $configuracion->gre_token_url ?: (string) config('sunat.gre.'.$this->ambienteKey($configuracion).'.token_url');
    }

    public function greApiUrl(SunatConfiguracion $configuracion): string
    {
        return $configuracion->gre_api_url ?: (string) config('sunat.gre.'.$this->ambienteKey($configuracion).'.api_url');
    }

    protected function aplicarDefaultsGre(array $data, ?SunatConfiguracion $configuracion = null): array
    {
        $ambiente = $data['ambiente'] ?? $configuracion?->ambiente ?? 'BETA';
        $key = strtolower($ambiente) === 'produccion' ? 'produccion' : 'beta';

        $data['gre_scope'] = $data['gre_scope'] ?? $configuracion?->gre_scope ?? config("sunat.gre.{$key}.scope");
        $data['gre_token_url'] = $data['gre_token_url'] ?? $configuracion?->gre_token_url ?? config("sunat.gre.{$key}.token_url");
        $data['gre_api_url'] = $data['gre_api_url'] ?? $configuracion?->gre_api_url ?? config("sunat.gre.{$key}.api_url");
        $data['gre_modo_envio'] = $data['gre_modo_envio'] ?? $configuracion?->gre_modo_envio ?? false;

        return $data;
    }

    protected function cifrarSecretosGre(array $data, bool $mantenerVacios): array
    {
        foreach (['gre_client_secret', 'gre_clave_sol'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (blank($data[$field])) {
                if ($mantenerVacios) {
                    unset($data[$field]);
                }
                continue;
            }

            $data[$field] = Crypt::encryptString($data[$field]);
        }

        return $data;
    }

    protected function ambienteKey(SunatConfiguracion $configuracion): string
    {
        return $configuracion->ambiente === 'PRODUCCION' ? 'produccion' : 'beta';
    }

    protected function validarSinConfiguracionActiva(int $empresaId, ?int $ignoreId = null): void
    {
        $query = SunatConfiguracion::where('empresa_id', $empresaId)->where('estado', true);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'empresa_id' => ['Ya existe una configuracion SUNAT activa para esta empresa.'],
            ]);
        }
    }
}