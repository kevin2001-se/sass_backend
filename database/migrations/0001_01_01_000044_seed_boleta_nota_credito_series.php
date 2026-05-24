<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fcSeries = DB::table('series_comprobantes')
            ->where('tipo_comprobante', 'NOTA_CREDITO')
            ->where('serie', 'FC01')
            ->get();

        foreach ($fcSeries as $serie) {
            DB::table('series_comprobantes')->updateOrInsert([
                'empresa_id' => $serie->empresa_id,
                'tienda_id' => $serie->tienda_id,
                'tipo_comprobante' => 'NOTA_CREDITO',
                'serie' => 'BC01',
            ], [
                'tenant_id' => $serie->tenant_id,
                'correlativo_actual' => 0,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('series_comprobantes')
            ->where('tipo_comprobante', 'NOTA_CREDITO')
            ->where('serie', 'BC01')
            ->delete();
    }
};