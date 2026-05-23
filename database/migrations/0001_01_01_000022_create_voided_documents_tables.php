<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicaciones_baja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->date('fecha_baja');
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

            $table->unique(['empresa_id', 'tienda_id', 'fecha_envio', 'correlativo'], 'baja_envio_correlativo_unique');
            $table->unique(['empresa_id', 'tienda_id', 'identificador'], 'baja_identificador_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'fecha_baja'], 'baja_contexto_fecha_idx');
            $table->index(['empresa_id', 'tienda_id', 'estado_sunat'], 'baja_estado_idx');
            $table->index('ticket', 'baja_ticket_idx');
        });

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->foreignId('comunicacion_baja_id')->nullable()->after('nota_electronica_id')->constrained('comunicaciones_baja')->nullOnDelete();
            $table->timestamp('dado_baja_at')->nullable()->after('rechazado_at');
            $table->index(['empresa_id', 'tienda_id', 'comunicacion_baja_id'], 'ce_comunicacion_baja_idx');
        });

        Schema::create('comunicacion_baja_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->foreignId('comunicacion_baja_id')->constrained('comunicaciones_baja')->cascadeOnDelete();
            $table->foreignId('comprobante_electronico_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
            $table->string('tipo_documento', 2);
            $table->string('serie', 10);
            $table->unsignedInteger('correlativo');
            $table->string('numero_comprobante', 20);
            $table->string('motivo_baja', 255);
            $table->timestamps();

            $table->unique(['comunicacion_baja_id', 'comprobante_electronico_id'], 'baja_detalle_item_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id'], 'baja_detalle_contexto_idx');
            $table->index(['comprobante_electronico_id'], 'baja_detalle_comprobante_idx');
            $table->index(['tipo_documento', 'serie', 'correlativo'], 'baja_detalle_documento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacion_baja_detalles');

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropIndex('ce_comunicacion_baja_idx');
            $table->dropConstrainedForeignId('comunicacion_baja_id');
            $table->dropColumn('dado_baja_at');
        });

        Schema::dropIfExists('comunicaciones_baja');
    }
};
