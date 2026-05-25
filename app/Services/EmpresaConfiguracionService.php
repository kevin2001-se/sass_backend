<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmpresaConfiguracionService
{
    public function actualizar(Empresa $empresa, array $data): Empresa
    {
        return DB::transaction(function () use ($empresa, $data) {
            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $data['logo_path'] = $this->guardarLogo($empresa, $data['logo']);
            }

            $estado = array_key_exists('estado', $data) ? (bool) $data['estado'] : (bool) ($empresa->estado ?? $empresa->active);

            $empresa->update([
                'ruc' => $data['ruc'],
                'nombre' => $data['razon_social'],
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'direccion' => $data['direccion_fiscal'],
                'direccion_fiscal' => $data['direccion_fiscal'],
                'ubigeo' => $data['ubigeo'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'email' => $data['email'] ?? null,
                'logo_path' => $data['logo_path'] ?? $empresa->logo_path,
                'estado' => $estado,
                'active' => $estado,
            ]);

            return $empresa->refresh();
        });
    }

    private function guardarLogo(Empresa $empresa, UploadedFile $logo): string
    {
        if ($empresa->logo_path) {
            Storage::disk('local')->delete($empresa->logo_path);
        }

        return $logo->storeAs(
            "private/empresas/{$empresa->id}",
            'logo.'.$logo->getClientOriginalExtension(),
            'local'
        );
    }
}