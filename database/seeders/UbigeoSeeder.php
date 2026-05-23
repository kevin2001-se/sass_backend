<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Provincia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = $this->readJson('departamentos.json');
        $provincias = $this->readJson('provincias.json');
        $distritos = $this->readJson('distritos.json');

        DB::transaction(function () use ($departamentos, $provincias, $distritos) {
            $departamentoIds = [];
            $provinciaIds = [];

            foreach ($departamentos as $row) {
                $codigo = $this->cleanCode($row['id'] ?? '', 2);
                if ($codigo === '') {
                    continue;
                }

                $departamento = Departamento::updateOrCreate(
                    ['codigo' => $codigo],
                    [
                        'nombre' => $this->cleanName($row['name'] ?? ''),
                        'estado' => true,
                    ]
                );

                $departamentoIds[$codigo] = $departamento->id;
            }

            foreach ($provincias as $row) {
                $codigo = $this->cleanCode($row['id'] ?? '', 4);
                $codigoDepartamento = $this->cleanCode($row['department_id'] ?? '', 2);

                if ($codigo === '' || ! isset($departamentoIds[$codigoDepartamento])) {
                    continue;
                }

                $provincia = Provincia::updateOrCreate(
                    ['codigo' => $codigo],
                    [
                        'departamento_id' => $departamentoIds[$codigoDepartamento],
                        'nombre' => $this->cleanName($row['name'] ?? ''),
                        'estado' => true,
                    ]
                );

                $provinciaIds[$codigo] = $provincia->id;
            }

            foreach ($distritos as $row) {
                $ubigeo = $this->cleanCode($row['id'] ?? '', 6);
                $codigoProvincia = $this->cleanCode($row['province_id'] ?? '', 4);

                if ($ubigeo === '' || ! isset($provinciaIds[$codigoProvincia])) {
                    continue;
                }

                Distrito::updateOrCreate(
                    ['ubigeo' => $ubigeo],
                    [
                        'provincia_id' => $provinciaIds[$codigoProvincia],
                        'codigo' => $ubigeo,
                        'nombre' => $this->cleanName($row['name'] ?? ''),
                        'estado' => true,
                    ]
                );
            }
        });
    }

    protected function readJson(string $filename): array
    {
        $path = database_path("seeders/data/ubigeo/{$filename}");

        if (! file_exists($path)) {
            throw new RuntimeException("No existe el archivo de ubigeo: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("El archivo de ubigeo no contiene un arreglo valido: {$path}");
        }

        return $data;
    }

    protected function cleanCode(string|int|null $value, int $length): string
    {
        $code = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($code === '') {
            return '';
        }

        return str_pad(substr($code, 0, $length), $length, '0', STR_PAD_LEFT);
    }

    protected function cleanName(string|null $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }
}