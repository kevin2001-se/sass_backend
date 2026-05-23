<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->string('nombre', 100);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->string('codigo', 4)->unique();
            $table->string('nombre', 100);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['departamento_id', 'estado']);
        });

        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('provincias')->cascadeOnDelete();
            $table->string('codigo', 6);
            $table->string('nombre', 100);
            $table->string('ubigeo', 6)->unique();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['provincia_id', 'estado']);
            $table->index(['nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distritos');
        Schema::dropIfExists('provincias');
        Schema::dropIfExists('departamentos');
    }
};
