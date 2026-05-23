<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias_remision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('serie', 10);
            $table->unsignedInteger('correlativo');
            $table->string('numero_guia', 20);
            $table->dateTime('fecha_emision');
            $table->date('fecha_traslado');
            $table->string('motivo_traslado_codigo', 2);
            $table->string('motivo_traslado_descripcion', 100);
            $table->string('modalidad_transporte', 2);
            $table->decimal('peso_total', 12, 3);
            $table->string('unidad_peso', 5)->default('KGM');
            $table->unsignedInteger('numero_bultos')->nullable();
            $table->string('punto_partida_ubigeo', 6);
            $table->string('punto_partida_direccion');
            $table->string('punto_llegada_ubigeo', 6);
            $table->string('punto_llegada_direccion');
            $table->string('transportista_tipo_documento', 20)->nullable();
            $table->string('transportista_numero_documento', 20)->nullable();
            $table->string('transportista_razon_social')->nullable();
            $table->string('conductor_tipo_documento', 20)->nullable();
            $table->string('conductor_numero_documento', 20)->nullable();
            $table->string('conductor_nombre')->nullable();
            $table->string('conductor_licencia', 30)->nullable();
            $table->string('vehiculo_placa', 20)->nullable();
            $table->string('estado', 20)->default('REGISTRADA');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'serie', 'correlativo'], 'guia_empresa_serie_correlativo_unique');
            $table->unique(['empresa_id', 'tienda_id', 'numero_guia'], 'guia_empresa_numero_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'fecha_emision'], 'guia_contexto_fecha_idx');
            $table->index(['empresa_id', 'tienda_id', 'estado'], 'guia_estado_idx');
            $table->index(['venta_id'], 'guia_venta_idx');
            $table->index(['compra_id'], 'guia_compra_idx');
        });

        Schema::create('guia_remision_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('guia_remision_id')->constrained('guias_remision')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained()->restrictOnDelete();
            $table->foreignId('producto_presentacion_id')->nullable()->constrained('producto_presentaciones')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad_medida', 10);
            $table->string('codigo_producto', 50)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id'], 'guia_detalle_contexto_idx');
            $table->index(['guia_remision_id'], 'guia_detalle_guia_idx');
            $table->index(['producto_id'], 'guia_detalle_producto_idx');
        });

        Schema::create('guia_remision_documentos_relacionados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->constrained()->restrictOnDelete();
            $table->foreignId('guia_remision_id')->constrained('guias_remision')->cascadeOnDelete();
            $table->string('tipo_documento', 20);
            $table->string('serie', 10);
            $table->string('numero', 20);
            $table->foreignId('comprobante_electronico_id')->nullable()->constrained('comprobantes_electronicos')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id'], 'guia_doc_contexto_idx');
            $table->index(['guia_remision_id'], 'guia_doc_guia_idx');
            $table->index(['comprobante_electronico_id'], 'guia_doc_comprobante_idx');
        });

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->foreignId('guia_remision_id')->nullable()->after('comunicacion_baja_id')->constrained('guias_remision')->nullOnDelete();
            $table->index(['empresa_id', 'tienda_id', 'guia_remision_id'], 'ce_guia_remision_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropIndex('ce_guia_remision_idx');
            $table->dropConstrainedForeignId('guia_remision_id');
        });

        Schema::dropIfExists('guia_remision_documentos_relacionados');
        Schema::dropIfExists('guia_remision_detalles');
        Schema::dropIfExists('guias_remision');
    }
};
