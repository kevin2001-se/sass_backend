<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN codigo_respuesta TYPE varchar(100)');
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN mensaje_respuesta TYPE text');
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN observacion TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN codigo_respuesta TYPE varchar(20)');
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN mensaje_respuesta TYPE text');
        DB::statement('ALTER TABLE guias_remision ALTER COLUMN observacion TYPE text');
    }
};