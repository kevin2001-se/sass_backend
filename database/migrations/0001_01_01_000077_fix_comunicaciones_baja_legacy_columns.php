<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comunicaciones_baja') && Schema::hasColumn('comunicaciones_baja', 'fecha_envio')) {
            DB::statement('ALTER TABLE comunicaciones_baja ALTER COLUMN fecha_envio DROP NOT NULL');
        }

        if (Schema::hasTable('comunicacion_baja_detalles') && Schema::hasColumn('comunicacion_baja_detalles', 'tipo_documento')) {
            DB::statement('ALTER TABLE comunicacion_baja_detalles ALTER COLUMN tipo_documento TYPE VARCHAR(20)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comunicacion_baja_detalles') && Schema::hasColumn('comunicacion_baja_detalles', 'tipo_documento')) {
            DB::statement('ALTER TABLE comunicacion_baja_detalles ALTER COLUMN tipo_documento TYPE VARCHAR(2)');
        }
    }
};
