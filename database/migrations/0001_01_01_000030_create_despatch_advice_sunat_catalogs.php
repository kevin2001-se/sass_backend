<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modalidades_transporte')) {
            Schema::create('modalidades_transporte', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 2)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('unidades_medida_sunat')) {
            Schema::create('unidades_medida_sunat', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        DB::table('modalidades_transporte')->insertOrIgnore([
            ['codigo' => '01', 'descripcion' => 'Transporte publico', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => '02', 'descripcion' => 'Transporte privado', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('unidades_medida_sunat')->insertOrIgnore([
            ['codigo' => 'NIU', 'descripcion' => 'Unidad', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'KGM', 'descripcion' => 'Kilogramo', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'GRM', 'descripcion' => 'Gramo', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'LTR', 'descripcion' => 'Litro', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'BX', 'descripcion' => 'Caja', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('guias_remision')->where('modalidad_transporte', 'PUBLICO')->update(['modalidad_transporte' => '01']);
        DB::table('guias_remision')->where('modalidad_transporte', 'PRIVADO')->update(['modalidad_transporte' => '02']);
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida_sunat');
        Schema::dropIfExists('modalidades_transporte');
    }
};