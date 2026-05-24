<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fdSeries = DB::table('series_comprobantes')
            ->where('tipo_comprobante', 'NOTA_DEBITO')
            ->where('serie', 'FD01')
            ->get();

        foreach ($fdSeries as $serie) {
            DB::table('series_comprobantes')->updateOrInsert([
                'empresa_id' => $serie->empresa_id,
                'tienda_id' => $serie->tienda_id,
                'tipo_comprobante' => 'NOTA_DEBITO',
                'serie' => 'BD01',
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
            ->where('tipo_comprobante', 'NOTA_DEBITO')
            ->where('serie', 'BD01')
            ->delete();
    }
};