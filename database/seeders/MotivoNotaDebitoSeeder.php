<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivoNotaDebitoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => '01', 'descripcion' => 'Intereses por mora'],
            ['codigo' => '02', 'descripcion' => 'Aumento en el valor'],
            ['codigo' => '03', 'descripcion' => 'Penalidades'],
        ] as $motivo) {
            DB::table('motivos_nota_debito')->updateOrInsert(
                ['codigo' => $motivo['codigo']],
                ['descripcion' => $motivo['descripcion'], 'estado' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}