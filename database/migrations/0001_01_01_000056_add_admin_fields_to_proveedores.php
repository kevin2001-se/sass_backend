<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedores', 'ubigeo')) {
                $table->string('ubigeo', 6)->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('proveedores', 'contacto')) {
                $table->string('contacto')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            if (Schema::hasColumn('proveedores', 'contacto')) {
                $table->dropColumn('contacto');
            }
            if (Schema::hasColumn('proveedores', 'ubigeo')) {
                $table->dropColumn('ubigeo');
            }
        });
    }
};