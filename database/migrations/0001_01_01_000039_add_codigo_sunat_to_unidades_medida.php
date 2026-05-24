<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unidades_medida', 'codigo_sunat')) {
            DB::statement('ALTER TABLE unidades_medida ADD COLUMN codigo_sunat varchar(10) NULL');
        }

        DB::statement("UPDATE unidades_medida SET codigo_sunat = COALESCE(codigo_sunat, CASE
            WHEN UPPER(abreviatura) IN ('UND', 'UNID', 'UNIDAD', 'NIU') THEN 'NIU'
            WHEN UPPER(abreviatura) IN ('CAJA', 'CJ', 'CJA', 'BX') THEN 'BX'
            WHEN UPPER(abreviatura) IN ('KG', 'KGM') THEN 'KGM'
            WHEN UPPER(abreviatura) IN ('G', 'GR', 'GRM') THEN 'GRM'
            WHEN UPPER(abreviatura) IN ('L', 'LT', 'LTR') THEN 'LTR'
            WHEN UPPER(abreviatura) IN ('PAQ', 'PACK', 'PK', 'BL', 'BLS', 'BLISTER') THEN 'PK'
            ELSE 'NIU'
        END)");
    }

    public function down(): void
    {
        if (Schema::hasColumn('unidades_medida', 'codigo_sunat')) {
            DB::statement('ALTER TABLE unidades_medida DROP COLUMN codigo_sunat');
        }
    }
};