<?php

namespace Database\Seeders;

use App\Models\UnidadMedidaSunat;
use Illuminate\Database\Seeder;

class UnidadMedidaSunatSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => 'NIU', 'descripcion' => 'Unidad'],
            ['codigo' => 'KGM', 'descripcion' => 'Kilogramo'],
            ['codigo' => 'GRM', 'descripcion' => 'Gramo'],
            ['codigo' => 'LTR', 'descripcion' => 'Litro'],
            ['codigo' => 'BX', 'descripcion' => 'Caja'],
        ] as $unidad) {
            UnidadMedidaSunat::updateOrCreate(
                ['codigo' => $unidad['codigo']],
                ['descripcion' => $unidad['descripcion'], 'estado' => true]
            );
        }
    }
}