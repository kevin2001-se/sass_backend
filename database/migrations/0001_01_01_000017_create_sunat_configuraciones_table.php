<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunat_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('ruc', 11);
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion_fiscal');
            $table->string('ubigeo', 6);
            $table->string('departamento', 100);
            $table->string('provincia', 100);
            $table->string('distrito', 100);
            $table->string('usuario_sol', 100);
            $table->text('clave_sol');
            $table->string('certificado_path')->nullable();
            $table->text('certificado_password')->nullable();
            $table->string('ambiente', 20);
            $table->string('modo_envio', 20);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'estado'], 'sunat_config_scope_estado_index');
            $table->index(['ruc', 'ambiente']);
        });

        DB::statement('CREATE UNIQUE INDEX sunat_config_empresa_activa_unique ON sunat_configuraciones (empresa_id) WHERE estado = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_configuraciones');
    }
};
