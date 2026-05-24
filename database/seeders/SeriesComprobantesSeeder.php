<?php

namespace Database\Seeders;

use App\Models\SerieComprobante;
use App\Models\Tienda;
use Illuminate\Database\Seeder;

class SeriesComprobantesSeeder extends Seeder
{
    public function run(): void
    {
        Tienda::with('empresa')->get()->each(function (Tienda $tienda) {
            $series = [
                ['tipo_comprobante' => 'NOTA_VENTA', 'serie' => 'NV01'],
                ['tipo_comprobante' => 'BOLETA', 'serie' => 'B001'],
                ['tipo_comprobante' => 'FACTURA', 'serie' => 'F001'],
                ['tipo_comprobante' => 'NOTA_CREDITO', 'serie' => 'FC01'],
                ['tipo_comprobante' => 'NOTA_CREDITO', 'serie' => 'BC01'],
                ['tipo_comprobante' => 'NOTA_DEBITO', 'serie' => 'FD01'],
                ['tipo_comprobante' => 'NOTA_DEBITO', 'serie' => 'BD01'],
                ['tipo_comprobante' => 'GUIA_REMISION', 'serie' => 'T001'],
            ];

            foreach ($series as $serie) {
                SerieComprobante::firstOrCreate([
                    'empresa_id' => $tienda->empresa_id,
                    'tienda_id' => $tienda->id,
                    'tipo_comprobante' => $serie['tipo_comprobante'],
                    'serie' => $serie['serie'],
                ], [
                    'tenant_id' => $tienda->empresa->tenant_id,
                    'correlativo_actual' => 0,
                    'estado' => true,
                ]);
            }
        });
    }
}
