<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunat_configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_client_id')) {
                $table->string('gre_client_id')->nullable()->after('modo_envio');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_client_secret')) {
                $table->text('gre_client_secret')->nullable()->after('gre_client_id');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_usuario_sol')) {
                $table->string('gre_usuario_sol', 100)->nullable()->after('gre_client_secret');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_clave_sol')) {
                $table->text('gre_clave_sol')->nullable()->after('gre_usuario_sol');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_scope')) {
                $table->string('gre_scope')->nullable()->after('gre_clave_sol');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_token_url')) {
                $table->string('gre_token_url')->nullable()->after('gre_scope');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_api_url')) {
                $table->string('gre_api_url')->nullable()->after('gre_token_url');
            }
            if (! Schema::hasColumn('sunat_configuraciones', 'gre_modo_envio')) {
                $table->boolean('gre_modo_envio')->default(false)->after('gre_api_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sunat_configuraciones', function (Blueprint $table) {
            foreach (['gre_modo_envio', 'gre_api_url', 'gre_token_url', 'gre_scope', 'gre_clave_sol', 'gre_usuario_sol', 'gre_client_secret', 'gre_client_id'] as $column) {
                if (Schema::hasColumn('sunat_configuraciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};