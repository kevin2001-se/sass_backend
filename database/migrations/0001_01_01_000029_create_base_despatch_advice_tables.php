<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('motivos_traslado')) {
            Schema::create('motivos_traslado', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 2)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        DB::table('motivos_traslado')->insertOrIgnore([
            ['codigo' => '01', 'descripcion' => 'Venta', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '02', 'descripcion' => 'Compra', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '04', 'descripcion' => 'Traslado entre establecimientos', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '08', 'descripcion' => 'Importacion', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '09', 'descripcion' => 'Exportacion', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '13', 'descripcion' => 'Otros', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('guias_remision', function (Blueprint $table) {
            if (! Schema::hasColumn('guias_remision', 'numero_completo')) {
                $table->string('numero_completo', 20)->nullable()->after('correlativo');
            }
            if (! Schema::hasColumn('guias_remision', 'destinatario_tipo_documento')) {
                $table->string('destinatario_tipo_documento', 20)->nullable()->after('cliente_id');
            }
            if (! Schema::hasColumn('guias_remision', 'destinatario_numero_documento')) {
                $table->string('destinatario_numero_documento', 20)->nullable()->after('destinatario_tipo_documento');
            }
            if (! Schema::hasColumn('guias_remision', 'destinatario_nombre')) {
                $table->string('destinatario_nombre')->nullable()->after('destinatario_numero_documento');
            }
            if (! Schema::hasColumn('guias_remision', 'transportista_ruc')) {
                $table->string('transportista_ruc', 11)->nullable()->after('transportista_numero_documento');
            }
            if (! Schema::hasColumn('guias_remision', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('guias_remision', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('guias_remision', 'numero_guia')) {
            DB::table('guias_remision')->whereNull('numero_completo')->update(['numero_completo' => DB::raw('numero_guia')]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE guias_remision ALTER COLUMN motivo_traslado_descripcion DROP NOT NULL');
            DB::statement('ALTER TABLE guia_remision_detalles ALTER COLUMN producto_id DROP NOT NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_contexto_idx ON guias_remision (tenant_id, empresa_id, tienda_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_numero_completo_idx ON guias_remision (numero_completo)');
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_fecha_emision_idx ON guias_remision (fecha_emision)');
            DB::statement('CREATE INDEX IF NOT EXISTS guias_remision_estado_idx ON guias_remision (estado)');
            DB::statement('CREATE INDEX IF NOT EXISTS guia_remision_detalles_guia_idx ON guia_remision_detalles (guia_remision_id)');
        }

        Schema::table('guia_remision_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('guia_remision_detalles', 'peso')) {
                $table->decimal('peso', 12, 3)->nullable()->after('cantidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('guia_remision_detalles', 'peso')) {
                $table->dropColumn('peso');
            }
        });

        Schema::table('guias_remision', function (Blueprint $table) {
            foreach (['numero_completo', 'destinatario_tipo_documento', 'destinatario_numero_documento', 'destinatario_nombre', 'transportista_ruc'] as $column) {
                if (Schema::hasColumn('guias_remision', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('guias_remision', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('guias_remision', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
        });

        Schema::dropIfExists('motivos_traslado');
    }
};