<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afectaciones_igv', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 4)->unique();
            $table->string('descripcion');
            $table->string('abreviatura', 10);
            $table->boolean('aplica_igv')->default(false);
            $table->boolean('es_gratuito')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['estado', 'codigo']);
        });

        DB::table('afectaciones_igv')->insertOrIgnore([
            ['codigo' => '10', 'descripcion' => 'Gravado - Operacion Onerosa', 'abreviatura' => 'IGV', 'aplica_igv' => true, 'es_gratuito' => false, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '20', 'descripcion' => 'Exonerado - Operacion Onerosa', 'abreviatura' => 'EXO', 'aplica_igv' => false, 'es_gratuito' => false, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '30', 'descripcion' => 'Inafecto - Operacion Onerosa', 'abreviatura' => 'INA', 'aplica_igv' => false, 'es_gratuito' => false, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '11', 'descripcion' => 'Gravado - Retiro por premio o donacion', 'abreviatura' => 'GRA', 'aplica_igv' => true, 'es_gratuito' => true, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('producto_configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('producto_configuraciones', 'autogenerar_codigo_interno')) {
                $table->boolean('autogenerar_codigo_interno')->default(true)->after('empresa_id');
            }
            if (! Schema::hasColumn('producto_configuraciones', 'prefijo_codigo_interno')) {
                $table->string('prefijo_codigo_interno', 20)->nullable()->default('PROD')->after('autogenerar_codigo_interno');
            }
            if (! Schema::hasColumn('producto_configuraciones', 'ultimo_correlativo_codigo_interno')) {
                $table->unsignedBigInteger('ultimo_correlativo_codigo_interno')->default(0)->after('prefijo_codigo_interno');
            }
        });

        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'afectacion_igv_id')) {
                $table->foreignId('afectacion_igv_id')->nullable()->after('afecto_igv')->constrained('afectaciones_igv')->nullOnDelete();
            }
        });

        $gravadoId = DB::table('afectaciones_igv')->where('codigo', '10')->value('id');
        $exoneradoId = DB::table('afectaciones_igv')->where('codigo', '20')->value('id');

        if ($gravadoId && $exoneradoId && Schema::hasColumn('productos', 'afecto_igv')) {
            DB::table('productos')
                ->whereNull('afectacion_igv_id')
                ->update(['afectacion_igv_id' => DB::raw("CASE WHEN afecto_igv = true THEN {$gravadoId} ELSE {$exoneradoId} END")]);
        }

        Schema::create('producto_principio_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('principio_activo_id')->constrained('principios_activos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'producto_id', 'principio_activo_id'], 'producto_principio_activo_unique');
            $table->index(['tenant_id', 'empresa_id', 'principio_activo_id'], 'ppa_scope_principio_index');
        });

        if (Schema::hasColumn('productos', 'principio_activo_id')) {
            DB::statement('INSERT INTO producto_principio_activo (tenant_id, empresa_id, producto_id, principio_activo_id, created_at, updated_at) SELECT tenant_id, empresa_id, id, principio_activo_id, NOW(), NOW() FROM productos WHERE principio_activo_id IS NOT NULL ON CONFLICT DO NOTHING');
        }

        if (Schema::hasColumn('lotes', 'tienda_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE lotes DROP CONSTRAINT IF EXISTS lotes_empresa_tienda_producto_codigo_unique');
                DB::statement('DROP INDEX IF EXISTS lotes_empresa_tienda_producto_codigo_unique');
                DB::statement('DROP INDEX IF EXISTS lotes_tenant_empresa_tienda_estado_index');
                DB::statement('DROP INDEX IF EXISTS lotes_pos_fefo_idx');

                DB::statement(<<<'SQL'
WITH duplicated AS (
    SELECT id, MIN(id) OVER (PARTITION BY empresa_id, producto_id, codigo_lote) AS keep_id
    FROM lotes
)
UPDATE stocks s SET lote_id = d.keep_id
FROM duplicated d
WHERE s.lote_id = d.id AND d.id <> d.keep_id
SQL);
                DB::statement(<<<'SQL'
WITH duplicated AS (
    SELECT id, MIN(id) OVER (PARTITION BY empresa_id, producto_id, codigo_lote) AS keep_id
    FROM lotes
)
UPDATE inventario_movimientos m SET lote_id = d.keep_id
FROM duplicated d
WHERE m.lote_id = d.id AND d.id <> d.keep_id
SQL);
                foreach (['venta_detalles', 'compra_detalles'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::statement("WITH duplicated AS (SELECT id, MIN(id) OVER (PARTITION BY empresa_id, producto_id, codigo_lote) AS keep_id FROM lotes) UPDATE {$table} t SET lote_id = d.keep_id FROM duplicated d WHERE t.lote_id = d.id AND d.id <> d.keep_id");
                    }
                }
                DB::statement('DELETE FROM lotes l USING (SELECT id, MIN(id) OVER (PARTITION BY empresa_id, producto_id, codigo_lote) AS keep_id FROM lotes) d WHERE l.id = d.id AND d.id <> d.keep_id');
            }

            Schema::table('lotes', function (Blueprint $table) {
                $table->dropForeign(['tienda_id']);
                $table->dropColumn('tienda_id');
            });
        }

        Schema::table('lotes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'producto_id', 'codigo_lote'], 'lotes_empresa_producto_codigo_unique');
            $table->index(['tenant_id', 'empresa_id', 'estado'], 'lotes_tenant_empresa_estado_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS lotes_pos_fefo_empresa_idx ON lotes (tenant_id, empresa_id, producto_id, estado, fecha_vencimiento)');
        }
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropUnique('lotes_empresa_producto_codigo_unique');
            $table->dropIndex('lotes_tenant_empresa_estado_index');
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS lotes_pos_fefo_empresa_idx');
            }
            if (! Schema::hasColumn('lotes', 'tienda_id')) {
                $table->foreignId('tienda_id')->nullable()->after('empresa_id')->constrained('tiendas')->nullOnDelete();
            }
        });

        Schema::dropIfExists('producto_principio_activo');

        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'afectacion_igv_id')) {
                $table->dropConstrainedForeignId('afectacion_igv_id');
            }
        });

        Schema::dropIfExists('afectaciones_igv');
    }
};
