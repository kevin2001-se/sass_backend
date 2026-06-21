<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            if (! Schema::hasColumn('resumenes_diarios', 'cdr_path')) {
                $table->string('cdr_path')->nullable()->after('xml_path');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'consultado_at')) {
                $table->timestamp('consultado_at')->nullable()->after('enviado_at');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'aceptado_at')) {
                $table->timestamp('aceptado_at')->nullable()->after('consultado_at');
            }
            if (! Schema::hasColumn('resumenes_diarios', 'rechazado_at')) {
                $table->timestamp('rechazado_at')->nullable()->after('aceptado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resumenes_diarios', function (Blueprint $table) {
            foreach (['consultado_at'] as $column) {
                if (Schema::hasColumn('resumenes_diarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};