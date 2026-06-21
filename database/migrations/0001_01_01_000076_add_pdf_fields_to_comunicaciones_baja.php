<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            return;
        }

        Schema::table('comunicaciones_baja', function (Blueprint $table) {
            if (! Schema::hasColumn('comunicaciones_baja', 'pdf_a4_path')) {
                $table->string('pdf_a4_path')->nullable()->after('cdr_path');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'pdf_generado_at')) {
                $table->timestamp('pdf_generado_at')->nullable()->after('pdf_a4_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            return;
        }

        Schema::table('comunicaciones_baja', function (Blueprint $table) {
            foreach (['pdf_generado_at', 'pdf_a4_path'] as $column) {
                if (Schema::hasColumn('comunicaciones_baja', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
