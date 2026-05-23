<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->string('pdf_a4_path')->nullable()->after('cdr_path');
            $table->string('ticket_80_path')->nullable()->after('pdf_a4_path');
            $table->string('ticket_58_path')->nullable()->after('ticket_80_path');
            $table->timestamp('pdf_generado_at')->nullable()->after('dado_baja_at');
            $table->timestamp('ticket_generado_at')->nullable()->after('pdf_generado_at');
            $table->index(['empresa_id', 'tienda_id', 'tipo_comprobante', 'fecha_emision'], 'ce_consulta_documentos_idx');
            $table->index(['empresa_id', 'tienda_id', 'estado_sunat'], 'ce_consulta_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropIndex('ce_consulta_documentos_idx');
            $table->dropIndex('ce_consulta_estado_idx');
            $table->dropColumn(['pdf_a4_path', 'ticket_80_path', 'ticket_58_path', 'pdf_generado_at', 'ticket_generado_at']);
        });
    }
};
