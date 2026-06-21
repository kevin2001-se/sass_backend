<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('resumen_diario_detalles', 'tipo_documento')) {
            DB::statement('ALTER TABLE resumen_diario_detalles ALTER COLUMN tipo_documento TYPE varchar(30)');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('resumen_diario_detalles', 'tipo_documento')) {
            DB::statement("UPDATE resumen_diario_detalles SET tipo_documento = CASE tipo_documento WHEN 'BOLETA' THEN '03' WHEN 'NOTA_CREDITO' THEN '07' WHEN 'NOTA_DEBITO' THEN '08' ELSE tipo_documento END");
            DB::statement('ALTER TABLE resumen_diario_detalles ALTER COLUMN tipo_documento TYPE varchar(2)');
        }
    }
};