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
            if (! Schema::hasColumn('comunicaciones_baja', 'cdr_path')) {
                $table->string('cdr_path')->nullable()->after('xml_path');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'consultado_at')) {
                $table->timestamp('consultado_at')->nullable()->after('enviado_at');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'aceptado_at')) {
                $table->timestamp('aceptado_at')->nullable()->after('consultado_at');
            }
            if (! Schema::hasColumn('comunicaciones_baja', 'rechazado_at')) {
                $table->timestamp('rechazado_at')->nullable()->after('aceptado_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            return;
        }

        Schema::table('comunicaciones_baja', function (Blueprint $table) {
            foreach (['consultado_at'] as $column) {
                if (Schema::hasColumn('comunicaciones_baja', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
