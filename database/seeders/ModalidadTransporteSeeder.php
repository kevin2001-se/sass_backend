<?php

namespace Database\Seeders;

use App\Models\ModalidadTransporte;
use Illuminate\Database\Seeder;

class ModalidadTransporteSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => '01', 'descripcion' => 'Transporte publico'],
            ['codigo' => '02', 'descripcion' => 'Transporte privado'],
        ] as $modalidad) {
            ModalidadTransporte::updateOrCreate(
                ['codigo' => $modalidad['codigo']],
                ['descripcion' => $modalidad['descripcion'], 'estado' => true]
            );
        }
    }
}