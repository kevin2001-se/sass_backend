<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_electronicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_comprobante', 20);
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->string('numero_comprobante', 30);
            $table->dateTime('fecha_emision');
            $table->string('moneda', 3)->default('PEN');
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('hash')->nullable();
            $table->text('qr_text')->nullable();
            $table->string('estado_sunat', 20)->default('PENDIENTE');
            $table->string('codigo_respuesta', 20)->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->string('ticket')->nullable();
            $table->unsignedInteger('intentos_envio')->default(0);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('aceptado_at')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'venta_id'], 'ce_empresa_venta_unique');
            $table->unique(['empresa_id', 'tipo_comprobante', 'serie', 'correlativo'], 'ce_comprobante_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado_sunat'], 'ce_scope_estado_index');
            $table->index(['fecha_emision', 'tipo_comprobante'], 'ce_fecha_tipo_index');
            $table->index(['numero_comprobante'], 'ce_numero_comprobante_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_electronicos');
    }
};
