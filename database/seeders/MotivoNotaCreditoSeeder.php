<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivoNotaCreditoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            ['codigo' => '01', 'descripcion' => 'Anulacion de la operacion'],
            ['codigo' => '02', 'descripcion' => 'Anulacion por error RUC'],
            ['codigo' => '03', 'descripcion' => 'Correccion por error descripcion'],
            ['codigo' => '07', 'descripcion' => 'Devolucion total'],
            ['codigo' => '08', 'descripcion' => 'Devolucion parcial'],
        ];

        foreach ($motivos as $motivo) {
            DB::table('motivos_nota_credito')->updateOrInsert(
                ['codigo' => $motivo['codigo']],
                [
                    'descripcion' => $motivo['descripcion'],
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
