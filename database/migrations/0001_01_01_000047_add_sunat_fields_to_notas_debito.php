<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_debito', function (Blueprint $table) {
            if (! Schema::hasColumn('notas_debito', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('anulado_at');
            }

            if (! Schema::hasColumn('notas_debito', 'cdr_path')) {
                $table->string('cdr_path')->nullable()->after('xml_path');
            }

            if (! Schema::hasColumn('notas_debito', 'hash')) {
                $table->string('hash')->nullable()->after('cdr_path');
            }

            if (! Schema::hasColumn('notas_debito', 'qr_text')) {
                $table->text('qr_text')->nullable()->after('hash');
            }

            if (! Schema::hasColumn('notas_debito', 'estado_sunat')) {
                $table->string('estado_sunat', 20)->default('PENDIENTE')->after('qr_text');
            }

            if (! Schema::hasColumn('notas_debito', 'codigo_respuesta')) {
                $table->string('codigo_respuesta', 20)->nullable()->after('estado_sunat');
            }

            if (! Schema::hasColumn('notas_debito', 'mensaje_respuesta')) {
                $table->text('mensaje_respuesta')->nullable()->after('codigo_respuesta');
            }

            if (! Schema::hasColumn('notas_debito', 'intentos_envio')) {
                $table->unsignedInteger('intentos_envio')->default(0)->after('mensaje_respuesta');
            }

            if (! Schema::hasColumn('notas_debito', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('intentos_envio');
            }

            if (! Schema::hasColumn('notas_debito', 'aceptado_at')) {
                $table->timestamp('aceptado_at')->nullable()->after('enviado_at');
            }

            if (! Schema::hasColumn('notas_debito', 'rechazado_at')) {
                $table->timestamp('rechazado_at')->nullable()->after('aceptado_at');
            }
        });

        Schema::table('notas_debito', function (Blueprint $table) {
            $table->index(['tenant_id', 'empresa_id', 'tienda_id', 'estado_sunat'], 'nd_scope_estado_sunat_index');
        });
    }

    public function down(): void
    {
        Schema::table('notas_debito', function (Blueprint $table) {
            $table->dropIndex('nd_scope_estado_sunat_index');

            $columns = collect([
                'xml_path',
                'cdr_path',
                'hash',
                'qr_text',
                'estado_sunat',
                'codigo_respuesta',
                'mensaje_respuesta',
                'intentos_envio',
                'enviado_at',
                'aceptado_at',
                'rechazado_at',
            ])->filter(fn (string $column) => Schema::hasColumn('notas_debito', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};