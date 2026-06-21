<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comunicaciones_baja')) {
            Schema::create('comunicaciones_baja', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $table->date('fecha_baja');
                $table->string('identificador', 20);
                $table->unsignedInteger('correlativo');
                $table->string('estado', 20)->default('REGISTRADA');
                $table->string('estado_sunat', 20)->default('PENDIENTE');
                $table->unsignedInteger('total_documentos')->default(0);
                $table->text('observacion')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('anulado_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('anulado_at')->nullable();
                $table->text('motivo_anulacion')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'cb_scope_idx');
                $table->index('fecha_baja', 'cb_fecha_baja_idx');
                $table->index('identificador', 'cb_identificador_idx');
                $table->index('estado', 'cb_estado_idx');
                $table->unique(['empresa_id', 'fecha_baja', 'correlativo'], 'cb_empresa_fecha_correlativo_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicaciones_baja');
    }
};
