<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->date('fecha_resumen');
            $table->date('fecha_envio');
            $table->unsignedInteger('correlativo');
            $table->string('identificador', 20);
            $table->string('estado_sunat', 20)->default('PENDIENTE');
            $table->string('ticket')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('codigo_respuesta')->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->unsignedInteger('intentos_envio')->default(0);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('aceptado_at')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'fecha_envio', 'correlativo'], 'resumen_envio_correlativo_unique');
            $table->unique(['empresa_id', 'tienda_id', 'identificador'], 'resumen_identificador_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'fecha_resumen'], 'resumen_contexto_fecha_idx');
            $table->index(['empresa_id', 'tienda_id', 'estado_sunat'], 'resumen_estado_idx');
            $table->index('ticket', 'resumen_ticket_idx');
        });

        Schema::create('resumen_diario_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->foreignId('resumen_diario_id')->constrained('resumenes_diarios')->cascadeOnDelete();
            $table->foreignId('comprobante_electronico_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
            $table->string('tipo_documento', 2);
            $table->string('serie', 10);
            $table->unsignedInteger('correlativo');
            $table->string('numero_comprobante', 20);
            $table->string('estado_item', 1)->default('1');
            $table->decimal('total', 12, 2);
            $table->decimal('total_igv', 12, 2);
            $table->timestamps();

            $table->unique(['resumen_diario_id', 'comprobante_electronico_id', 'estado_item'], 'resumen_detalle_item_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'resumen_detalle_contexto_idx');
            $table->index(['comprobante_electronico_id', 'estado_item'], 'resumen_detalle_comprobante_idx');
            $table->index(['tipo_documento', 'serie', 'correlativo'], 'resumen_detalle_documento_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('resumen_diario_detalles');
        Schema::dropIfExists('resumenes_diarios');
    }
};
