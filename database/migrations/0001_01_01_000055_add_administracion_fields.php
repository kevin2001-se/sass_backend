<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'razon_social')) {
                $table->string('razon_social')->nullable()->after('ruc');
            }
            if (! Schema::hasColumn('empresas', 'nombre_comercial')) {
                $table->string('nombre_comercial')->nullable()->after('razon_social');
            }
            if (! Schema::hasColumn('empresas', 'direccion_fiscal')) {
                $table->string('direccion_fiscal')->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('empresas', 'ubigeo')) {
                $table->string('ubigeo', 6)->nullable()->after('direccion_fiscal');
            }
            if (! Schema::hasColumn('empresas', 'telefono')) {
                $table->string('telefono')->nullable()->after('ubigeo');
            }
            if (! Schema::hasColumn('empresas', 'email')) {
                $table->string('email')->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('empresas', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('email');
            }
            if (! Schema::hasColumn('empresas', 'estado')) {
                $table->boolean('estado')->default(true)->after('logo_path');
            }
        });

        DB::table('empresas')
            ->whereNull('razon_social')
            ->update([
                'razon_social' => DB::raw('nombre'),
                'direccion_fiscal' => DB::raw('direccion'),
                'estado' => DB::raw('active'),
            ]);

        Schema::table('tiendas', function (Blueprint $table) {
            if (! Schema::hasColumn('tiendas', 'ubigeo')) {
                $table->string('ubigeo', 6)->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('tiendas', 'telefono')) {
                $table->string('telefono')->nullable()->after('ubigeo');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'estado')) {
                $table->boolean('estado')->default(true)->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'estado')) {
                $table->dropColumn('estado');
            }
        });

        Schema::table('tiendas', function (Blueprint $table) {
            foreach (['telefono', 'ubigeo'] as $column) {
                if (Schema::hasColumn('tiendas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('empresas', function (Blueprint $table) {
            foreach (['estado', 'logo_path', 'email', 'telefono', 'ubigeo', 'direccion_fiscal', 'nombre_comercial', 'razon_social'] as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
