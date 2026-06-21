<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave');
            $table->text('valor')->nullable();
            $table->string('tipo', 20);
            $table->string('grupo', 50);
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'clave'], 'parametros_empresa_clave_unique');
            $table->index(['tenant_id', 'empresa_id', 'grupo', 'estado'], 'parametros_scope_grupo_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};