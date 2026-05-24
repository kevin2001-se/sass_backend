<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->boolean('stock_aplicado')->default(false)->after('afecta_caja');
            $table->boolean('caja_aplicada')->default(false)->after('stock_aplicado');
            $table->timestamp('stock_aplicado_at')->nullable()->after('caja_aplicada');
            $table->timestamp('caja_aplicada_at')->nullable()->after('stock_aplicado_at');
            $table->foreignId('caja_movimiento_id')->nullable()->after('caja_aplicada_at')->constrained('caja_movimientos')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caja_movimiento_id');
            $table->dropColumn([
                'stock_aplicado',
                'caja_aplicada',
                'stock_aplicado_at',
                'caja_aplicada_at',
                'motivo_anulacion',
            ]);
        });
    }
};
