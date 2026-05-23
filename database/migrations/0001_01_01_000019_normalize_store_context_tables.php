<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            if (! Schema::hasColumn('tiendas', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            }
        });

        DB::statement('UPDATE tiendas SET tenant_id = (SELECT tenant_id FROM empresas WHERE empresas.id = tiendas.empresa_id) WHERE tenant_id IS NULL');

        if (Schema::hasColumn('tiendas', 'active') && ! Schema::hasColumn('tiendas', 'estado')) {
            Schema::table('tiendas', fn (Blueprint $table) => $table->renameColumn('active', 'estado'));
        }

        if (Schema::hasColumn('users', 'active_tienda_id') && ! Schema::hasColumn('users', 'tienda_activa_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->renameColumn('active_tienda_id', 'tienda_activa_id'));
        }

        Schema::create('tienda_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['tienda_id', 'user_id']);
            $table->index(['tenant_id', 'empresa_id', 'estado']);
            $table->index(['user_id', 'estado']);
        });

        DB::statement('INSERT INTO tienda_user (tenant_id, empresa_id, tienda_id, user_id, estado, created_at, updated_at)
            SELECT users.tenant_id, users.empresa_id, users.tienda_activa_id, users.id, true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM users
            WHERE users.tienda_activa_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tienda_user');

        if (Schema::hasColumn('users', 'tienda_activa_id') && ! Schema::hasColumn('users', 'active_tienda_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->renameColumn('tienda_activa_id', 'active_tienda_id'));
        }

        if (Schema::hasColumn('tiendas', 'estado') && ! Schema::hasColumn('tiendas', 'active')) {
            Schema::table('tiendas', fn (Blueprint $table) => $table->renameColumn('estado', 'active'));
        }

        Schema::table('tiendas', function (Blueprint $table) {
            if (Schema::hasColumn('tiendas', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
