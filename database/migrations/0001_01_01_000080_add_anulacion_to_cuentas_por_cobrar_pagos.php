<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_por_cobrar_pagos')) {
            return;
        }

        Schema::table('cuentas_por_cobrar_pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('cuentas_por_cobrar_pagos', 'anulado_by')) {
                $table->foreignId('anulado_by')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('cuentas_por_cobrar_pagos', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('anulado_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuentas_por_cobrar_pagos')) {
            return;
        }

        Schema::table('cuentas_por_cobrar_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('cuentas_por_cobrar_pagos', 'anulado_at')) {
                $table->dropColumn('anulado_at');
            }

            if (Schema::hasColumn('cuentas_por_cobrar_pagos', 'anulado_by')) {
                $table->dropConstrainedForeignId('anulado_by');
            }
        });
    }
};