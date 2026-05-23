<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->boolean('autogenerar_codigo_barra')->default(false);
            $table->string('prefijo_codigo_barra')->nullable();
            $table->unsignedBigInteger('ultimo_correlativo_codigo_barra')->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique('empresa_id', 'producto_config_empresa_unique');
            $table->index(['tenant_id', 'empresa_id', 'estado'], 'producto_config_tenant_empresa_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_configuraciones');
    }
};
