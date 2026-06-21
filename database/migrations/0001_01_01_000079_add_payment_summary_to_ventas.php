<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ventas')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'monto_pagado')) {
                $table->decimal('monto_pagado', 14, 2)->default(0)->after('total');
            }

            if (! Schema::hasColumn('ventas', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 14, 2)->default(0)->after('monto_pagado');
            }
        });

        if (Schema::hasTable('venta_pagos')) {
            DB::statement("UPDATE ventas SET monto_pagado = COALESCE((SELECT SUM(monto) FROM venta_pagos WHERE venta_pagos.venta_id = ventas.id AND venta_pagos.estado = 'REGISTRADO'), 0) WHERE monto_pagado = 0");
            DB::statement("UPDATE ventas SET saldo_pendiente = GREATEST(total - monto_pagado, 0)");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ventas')) {
            return;
        }

        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'saldo_pendiente')) {
                $table->dropColumn('saldo_pendiente');
            }

            if (Schema::hasColumn('ventas', 'monto_pagado')) {
                $table->dropColumn('monto_pagado');
            }
        });
    }
};