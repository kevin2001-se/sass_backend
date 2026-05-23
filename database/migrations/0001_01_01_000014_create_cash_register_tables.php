<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('user_apertura_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_cierre_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('monto_apertura', 14, 2);
            $table->decimal('monto_cierre_sistema', 14, 2)->default(0);
            $table->decimal('monto_cierre_real', 14, 2)->nullable();
            $table->decimal('diferencia', 14, 2)->nullable();
            $table->string('estado', 20)->default('ABIERTA');
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado'], 'cajas_scope_estado_index');
            $table->index(['fecha_apertura', 'fecha_cierre']);
        });

        DB::statement("CREATE UNIQUE INDEX cajas_tienda_abierta_unique ON cajas (tienda_id) WHERE estado = 'ABIERTA'");

        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo_movimiento', 30);
            $table->string('metodo_pago', 30);
            $table->string('concepto');
            $table->decimal('monto', 14, 2);
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'caja_id'], 'cm_scope_caja_index');
            $table->index(['tipo_movimiento', 'metodo_pago'], 'cm_tipo_metodo_index');
            $table->index(['referencia_tipo', 'referencia_id'], 'cm_referencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('cajas');
    }
};
