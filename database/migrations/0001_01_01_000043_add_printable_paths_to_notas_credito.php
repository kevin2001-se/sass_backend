<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            if (! Schema::hasColumn('notas_credito', 'pdf_a4_path')) {
                $table->string('pdf_a4_path')->nullable()->after('cdr_path');
            }

            if (! Schema::hasColumn('notas_credito', 'ticket_80_path')) {
                $table->string('ticket_80_path')->nullable()->after('pdf_a4_path');
            }

            if (! Schema::hasColumn('notas_credito', 'pdf_generado_at')) {
                $table->timestamp('pdf_generado_at')->nullable()->after('rechazado_at');
            }

            if (! Schema::hasColumn('notas_credito', 'ticket_generado_at')) {
                $table->timestamp('ticket_generado_at')->nullable()->after('pdf_generado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $columns = collect([
                'pdf_a4_path',
                'ticket_80_path',
                'pdf_generado_at',
                'ticket_generado_at',
            ])->filter(fn (string $column) => Schema::hasColumn('notas_credito', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};