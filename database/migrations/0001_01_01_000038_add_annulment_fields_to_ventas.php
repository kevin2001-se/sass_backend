<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ventas ADD COLUMN IF NOT EXISTS motivo_anulacion text NULL');
        DB::statement('ALTER TABLE ventas ADD COLUMN IF NOT EXISTS anulado_at timestamp NULL');
        DB::statement('ALTER TABLE ventas ADD COLUMN IF NOT EXISTS anulado_by bigint NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS ventas_anulado_by_index ON ventas (anulado_by)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ventas_anulado_by_index');
        DB::statement('ALTER TABLE ventas DROP COLUMN IF EXISTS motivo_anulacion');
        DB::statement('ALTER TABLE ventas DROP COLUMN IF EXISTS anulado_at');
        DB::statement('ALTER TABLE ventas DROP COLUMN IF EXISTS anulado_by');
    }
};