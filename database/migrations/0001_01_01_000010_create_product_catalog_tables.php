<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['categorias', 'marcas', 'laboratorios', 'principios_activos', 'acciones_terapeuticas'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'nombre']);
                $table->index(['tenant_id', 'empresa_id', 'estado']);
            });
        }

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('abreviatura')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
            $table->index(['tenant_id', 'empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('acciones_terapeuticas');
        Schema::dropIfExists('principios_activos');
        Schema::dropIfExists('laboratorios');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('categorias');
    }
};
