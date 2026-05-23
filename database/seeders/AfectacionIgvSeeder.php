<?php

namespace Database\Seeders;

use App\Models\AfectacionIgv;
use Illuminate\Database\Seeder;

class AfectacionIgvSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => '10', 'descripcion' => 'Gravado - Operacion Onerosa', 'abreviatura' => 'IGV', 'aplica_igv' => true, 'es_gratuito' => false],
            ['codigo' => '20', 'descripcion' => 'Exonerado - Operacion Onerosa', 'abreviatura' => 'EXO', 'aplica_igv' => false, 'es_gratuito' => false],
            ['codigo' => '30', 'descripcion' => 'Inafecto - Operacion Onerosa', 'abreviatura' => 'INA', 'aplica_igv' => false, 'es_gratuito' => false],
            ['codigo' => '11', 'descripcion' => 'Gravado - Retiro por premio o donacion', 'abreviatura' => 'GRA', 'aplica_igv' => true, 'es_gratuito' => true],
        ] as $item) {
            AfectacionIgv::updateOrCreate(['codigo' => $item['codigo']], array_merge($item, ['estado' => true]));
        }
    }
}
