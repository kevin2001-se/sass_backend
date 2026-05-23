<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_electronicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comprobante_referencia_id')->constrained('comprobantes_electronicos')->restrictOnDelete();
            $table->string('tipo_nota', 20);
            $table->string('serie', 10);
            $table->unsignedBigInteger('correlativo');
            $table->string('numero_comprobante', 30);
            $table->string('motivo_codigo', 2);
            $table->string('motivo_descripcion');
            $table->dateTime('fecha_emision');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('estado', 20)->default('REGISTRADA');
            $table->boolean('afecta_stock')->default(false);
            $table->boolean('afecta_caja')->default(false);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tienda_id', 'tipo_nota', 'serie', 'correlativo'], 'notas_numero_unique');
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'notas_scope_estado_index');
            $table->index(['comprobante_referencia_id', 'tipo_nota'], 'notas_referencia_tipo_index');
        });

        Schema::create('nota_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nota_electronica_id')->constrained('notas_electronicas')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->nullable()->constrained('venta_detalles')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('producto_presentacion_id')->nullable()->constrained('producto_presentaciones')->nullOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad_presentacion', 12, 4);
            $table->decimal('factor_conversion', 12, 4);
            $table->decimal('cantidad_base', 12, 4);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'nota_electronica_id'], 'nota_detalles_scope_index');
            $table->index(['producto_id', 'lote_id'], 'nota_detalles_producto_lote_index');
        });

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->foreignId('nota_electronica_id')->nullable()->after('venta_id')->constrained('notas_electronicas')->nullOnDelete();
            $table->string('documento_origen_tipo', 30)->nullable()->after('nota_electronica_id');
            $table->unsignedBigInteger('documento_origen_id')->nullable()->after('documento_origen_tipo');
            $table->index(['documento_origen_tipo', 'documento_origen_id'], 'ce_documento_origen_index');
        });

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->dropUnique('ce_empresa_venta_unique');
        });

        DB::statement('CREATE UNIQUE INDEX ce_empresa_venta_solo_venta_unique ON comprobantes_electronicos (empresa_id, venta_id) WHERE nota_electronica_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX ce_empresa_nota_unique ON comprobantes_electronicos (empresa_id, nota_electronica_id) WHERE nota_electronica_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ce_empresa_nota_unique');
        DB::statement('DROP INDEX IF EXISTS ce_empresa_venta_solo_venta_unique');

        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'venta_id'], 'ce_empresa_venta_unique');
            $table->dropIndex('ce_documento_origen_index');
            $table->dropConstrainedForeignId('nota_electronica_id');
            $table->dropColumn(['documento_origen_tipo', 'documento_origen_id']);
        });

        Schema::dropIfExists('nota_detalles');
        Schema::dropIfExists('notas_electronicas');
    }
};
