<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            if (! Schema::hasColumn('resumenes_diarios', 'pdf_a4_path')) {
                $table->string('pdf_a4_path')->nullable()->after('cdr_path');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'pdf_generado_at')) {
                $table->timestamp('pdf_generado_at')->nullable()->after('pdf_a4_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            foreach (['pdf_a4_path', 'pdf_generado_at'] as $column) {
                if (Schema::hasColumn('resumenes_diarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};