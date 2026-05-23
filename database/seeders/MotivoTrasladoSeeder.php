<?php

namespace Database\Seeders;

use App\Models\MotivoTraslado;
use Illuminate\Database\Seeder;

class MotivoTrasladoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => '01', 'descripcion' => 'Venta'],
            ['codigo' => '02', 'descripcion' => 'Compra'],
            ['codigo' => '04', 'descripcion' => 'Traslado entre establecimientos'],
            ['codigo' => '08', 'descripcion' => 'Importacion'],
            ['codigo' => '09', 'descripcion' => 'Exportacion'],
            ['codigo' => '13', 'descripcion' => 'Otros'],
        ] as $motivo) {
            MotivoTraslado::updateOrCreate(
                ['codigo' => $motivo['codigo']],
                ['descripcion' => $motivo['descripcion'], 'estado' => true]
            );
        }
    }
}