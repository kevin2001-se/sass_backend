<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_bajas_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comprobante_id')->constrained('comprobantes_electronicos')->cascadeOnDelete();
            $table->string('estado_anterior', 20);
            $table->string('estado_nuevo', 20);
            $table->text('motivo');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'empresa_id', 'comprobante_id'], 'cbh_scope_comprobante_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_bajas_historial');
    }
};