<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (! Schema::hasColumn('compras', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('motivo_anulacion');
            }
            if (! Schema::hasColumn('compras', 'pdf_generado_at')) {
                $table->timestamp('pdf_generado_at')->nullable()->after('pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'pdf_generado_at')) {
                $table->dropColumn('pdf_generado_at');
            }
            if (Schema::hasColumn('compras', 'pdf_path')) {
                $table->dropColumn('pdf_path');
            }
        });
    }
};
