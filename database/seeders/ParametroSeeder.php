<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Parametro;
use Illuminate\Database\Seeder;

class ParametroSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['grupo' => 'ventas', 'clave' => 'permitir_venta_sin_stock', 'valor' => false, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Permite registrar ventas aunque no exista stock suficiente.'],
            ['grupo' => 'ventas', 'clave' => 'aplicar_igv_en_nota_venta', 'valor' => false, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Aplica IGV referencial en notas de venta.'],
            ['grupo' => 'pos', 'clave' => 'imprimir_ticket_automatico', 'valor' => true, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Imprime ticket automaticamente al registrar venta POS.'],
            ['grupo' => 'inventario', 'clave' => 'metodo_salida', 'valor' => 'FEFO', 'tipo' => Parametro::TIPO_STRING, 'descripcion' => 'Metodo sugerido para salida de inventario.'],
            ['grupo' => 'inventario', 'clave' => 'dias_alerta_vencimiento', 'valor' => 30, 'tipo' => Parametro::TIPO_INTEGER, 'descripcion' => 'Dias previos para alerta de vencimiento.'],
            ['grupo' => 'compras', 'clave' => 'crear_cxp_automaticamente', 'valor' => true, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Crea cuenta por pagar automaticamente en compras a credito.'],
            ['grupo' => 'sistema', 'clave' => 'moneda_default', 'valor' => 'PEN', 'tipo' => Parametro::TIPO_STRING, 'descripcion' => 'Moneda por defecto del sistema.'],
            ['grupo' => 'sistema', 'clave' => 'decimales_montos', 'valor' => 2, 'tipo' => Parametro::TIPO_INTEGER, 'descripcion' => 'Cantidad de decimales para importes monetarios.'],
            ['grupo' => 'sunat', 'clave' => 'enviar_boleta_automaticamente', 'valor' => false, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Envia boletas automaticamente a SUNAT.'],
            ['grupo' => 'sunat', 'clave' => 'enviar_factura_automaticamente', 'valor' => false, 'tipo' => Parametro::TIPO_BOOLEAN, 'descripcion' => 'Envia facturas automaticamente a SUNAT.'],
        ];

        Empresa::query()->each(function (Empresa $empresa) use ($items) {
            foreach ($items as $item) {
                Parametro::updateOrCreate(
                    ['empresa_id' => $empresa->id, 'clave' => $item['clave']],
                    [
                        'tenant_id' => $empresa->tenant_id,
                        'valor' => $this->normalize($item['valor'], $item['tipo']),
                        'tipo' => $item['tipo'],
                        'grupo' => $item['grupo'],
                        'descripcion' => $item['descripcion'],
                        'estado' => true,
                    ]
                );
            }
        });
    }

    protected function normalize(mixed $valor, string $tipo): ?string
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            Parametro::TIPO_BOOLEAN => $valor ? 'true' : 'false',
            Parametro::TIPO_INTEGER => (string) ((int) $valor),
            Parametro::TIPO_DECIMAL => (string) ((float) $valor),
            Parametro::TIPO_JSON => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $valor,
        };
    }
}