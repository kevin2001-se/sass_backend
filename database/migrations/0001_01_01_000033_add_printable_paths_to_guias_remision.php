<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            if (! Schema::hasColumn('guias_remision', 'pdf_a4_path')) {
                $table->string('pdf_a4_path')->nullable()->after('rechazado_at');
            }
            if (! Schema::hasColumn('guias_remision', 'ticket_80_path')) {
                $table->string('ticket_80_path')->nullable()->after('pdf_a4_path');
            }
            if (! Schema::hasColumn('guias_remision', 'pdf_generado_at')) {
                $table->timestamp('pdf_generado_at')->nullable()->after('ticket_80_path');
            }
            if (! Schema::hasColumn('guias_remision', 'ticket_generado_at')) {
                $table->timestamp('ticket_generado_at')->nullable()->after('pdf_generado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guias_remision', function (Blueprint $table) {
            foreach (['pdf_a4_path', 'ticket_80_path', 'pdf_generado_at', 'ticket_generado_at'] as $column) {
                if (Schema::hasColumn('guias_remision', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};