<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comunicacion_baja_detalles')) {
            Schema::create('comunicacion_baja_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('comunicacion_baja_id')->constrained('comunicaciones_baja')->cascadeOnDelete();
                $table->foreignId('comprobante_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
                $table->string('tipo_documento', 20);
                $table->string('serie', 10);
                $table->unsignedInteger('correlativo');
                $table->string('numero_completo', 30);
                $table->text('motivo_baja');
                $table->timestamps();

                $table->index('comunicacion_baja_id', 'cbd_comunicacion_idx');
                $table->index(['comprobante_id', 'tipo_documento'], 'cbd_comprobante_tipo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacion_baja_detalles');
    }
};
