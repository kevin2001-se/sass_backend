<?php

namespace Database\Seeders;

use App\Models\AccionTerapeutica;
use App\Models\AfectacionIgv;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Laboratorio;
use App\Models\Lote;
use App\Models\Marca;
use App\Models\Permission;
use App\Models\PrincipioActivo;
use App\Models\Producto;
use App\Models\ProductoConfiguracion;
use App\Models\ProductoPresentacion;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Tienda;
use App\Models\Tenant;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AfectacionIgvSeeder::class,
            MotivoTrasladoSeeder::class,
            ModalidadTransporteSeeder::class,
            UnidadMedidaSunatSeeder::class,
            UbigeoSeeder::class,
            MotivoNotaCreditoSeeder::class,
            MotivoNotaDebitoSeeder::class,
        ]);

        $tenant = Tenant::updateOrCreate(
            ['slug' => 'demo-tenant'],
            ['name' => 'Demo Tenant', 'active' => true]
        );

        $empresa = Empresa::updateOrCreate(
            ['ruc' => '20161515648'],
            [
                'tenant_id' => $tenant->id,
                'nombre' => 'BOTICA DEMO SAC',
                'direccion' => 'AV. DEMO 123, LIMA',
                'active' => true,
            ]
        );

        $tiendas = $this->crearTiendas($tenant, $empresa);
        $tiendaPrincipal = $tiendas->first();

        $this->crearCatalogos($tenant, $empresa);
        $this->crearProductoConfiguracion($tenant, $empresa);
        $this->crearClientes($tenant, $empresa);

        $permissions = $this->crearPermisos();
        $roles = $this->crearRoles($empresa, $permissions);
        $this->crearUsuarios($tenant, $empresa, $tiendas, $roles);

        $this->call([
            ComprobantesPermissionsSeeder::class,
            SeriesComprobantesSeeder::class,
        ]);

        $this->syncAdminAllPermissions($empresa);
        $this->crearProductosDemo($tenant, $empresa, $tiendas);
    }

    private function crearTiendas(Tenant $tenant, Empresa $empresa): Collection
    {
        return collect([
            ['nombre' => 'Botica Principal', 'codigo' => 'BOT-001', 'direccion' => 'AV. DEMO 123', 'ubigeo' => '150101'],
            ['nombre' => 'Botica Norte', 'codigo' => 'BOT-002', 'direccion' => 'AV. LOS OLIVOS 456', 'ubigeo' => '150117'],
            ['nombre' => 'Botica Sur', 'codigo' => 'BOT-003', 'direccion' => 'AV. SAN JUAN 789', 'ubigeo' => '150132'],
        ])->map(function (array $data) use ($tenant, $empresa) {
            $tienda = Tienda::updateOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => $data['codigo']],
                [
                    'tenant_id' => $tenant->id,
                    'nombre' => $data['nombre'],
                    'direccion' => $data['direccion'],
                    'estado' => true,
                ]
            );

            if (Schema::hasColumn('tiendas', 'ubigeo')) {
                $tienda->forceFill(['ubigeo' => $data['ubigeo']])->save();
            }

            return $tienda;
        });
    }

    private function crearCatalogos(Tenant $tenant, Empresa $empresa): void
    {
        $base = ['tenant_id' => $tenant->id, 'empresa_id' => $empresa->id, 'estado' => true];

        $this->upsertCatalogo(Categoria::class, $base, [
            ['nombre' => 'Analgesicos', 'descripcion' => 'Medicamentos para dolor y fiebre'],
            ['nombre' => 'Antibioticos', 'descripcion' => 'Medicamentos antimicrobianos'],
            ['nombre' => 'Antigripales', 'descripcion' => 'Productos para resfrio y gripe'],
            ['nombre' => 'Gastrointestinales', 'descripcion' => 'Tratamientos digestivos'],
            ['nombre' => 'Material medico', 'descripcion' => 'Insumos y dispositivos medicos'],
        ]);

        $this->upsertCatalogo(Marca::class, $base, [
            ['nombre' => 'Generico', 'descripcion' => 'Producto sin marca comercial'],
            ['nombre' => 'Panadol', 'descripcion' => 'Marca de analgesicos'],
            ['nombre' => 'Bayer', 'descripcion' => 'Laboratorio y marca internacional'],
        ]);

        $this->upsertCatalogo(Laboratorio::class, $base, [
            ['nombre' => 'Medifarma', 'descripcion' => 'Laboratorio farmaceutico peruano'],
            ['nombre' => 'Portugal', 'descripcion' => 'Laboratorio de productos farmaceuticos'],
            ['nombre' => 'Bayer', 'descripcion' => 'Laboratorio internacional'],
        ]);

        $this->upsertCatalogo(PrincipioActivo::class, $base, [
            ['nombre' => 'Paracetamol', 'descripcion' => 'Analgesico y antipiretico'],
            ['nombre' => 'Ibuprofeno', 'descripcion' => 'Antiinflamatorio no esteroideo'],
            ['nombre' => 'Amoxicilina', 'descripcion' => 'Antibiotico betalactamico'],
        ]);

        $this->upsertCatalogo(AccionTerapeutica::class, $base, [
            ['nombre' => 'Analgesico', 'descripcion' => 'Alivio del dolor'],
            ['nombre' => 'Antibiotico', 'descripcion' => 'Tratamiento de infecciones bacterianas'],
            ['nombre' => 'Antiinflamatorio', 'descripcion' => 'Reduce inflamacion'],
        ]);

        foreach ([
            ['nombre' => 'Unidad', 'abreviatura' => 'UND', 'codigo_sunat' => 'NIU'],
            ['nombre' => 'Caja', 'abreviatura' => 'CAJ', 'codigo_sunat' => 'BX'],
            ['nombre' => 'Blister', 'abreviatura' => 'BLI', 'codigo_sunat' => 'NIU'],
            ['nombre' => 'Frasco', 'abreviatura' => 'FCO', 'codigo_sunat' => 'NIU'],
        ] as $item) {
            $unidad = UnidadMedida::updateOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => $item['nombre']],
                array_merge($base, ['abreviatura' => $item['abreviatura']])
            );

            if (Schema::hasColumn('unidades_medida', 'codigo_sunat')) {
                $unidad->forceFill(['codigo_sunat' => $item['codigo_sunat']])->save();
            }
        }
    }

    private function upsertCatalogo(string $modelClass, array $base, array $items): void
    {
        foreach ($items as $item) {
            $modelClass::updateOrCreate(
                ['empresa_id' => $base['empresa_id'], 'nombre' => $item['nombre']],
                array_merge($base, $item)
            );
        }
    }

    private function crearProductoConfiguracion(Tenant $tenant, Empresa $empresa): void
    {
        ProductoConfiguracion::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'tenant_id' => $tenant->id,
                'autogenerar_codigo_interno' => true,
                'prefijo_codigo_interno' => 'PROD',
                'ultimo_correlativo_codigo_interno' => 0,
                'autogenerar_codigo_barra' => true,
                'prefijo_codigo_barra' => 'BOT',
                'ultimo_correlativo_codigo_barra' => 0,
                'estado' => true,
            ]
        );
    }

    private function crearClientes(Tenant $tenant, Empresa $empresa): void
    {
        foreach ([
            ['tipo_documento' => 'SIN_DOCUMENTO', 'numero_documento' => '00000000', 'nombres' => 'Clientes varios', 'razon_social' => null, 'direccion' => null],
            ['tipo_documento' => 'DNI', 'numero_documento' => '12345678', 'nombres' => 'Juan Cliente', 'razon_social' => null, 'direccion' => 'AV. CLIENTE 123'],
            ['tipo_documento' => 'RUC', 'numero_documento' => '20123456789', 'nombres' => 'BOTICA DESTINO SAC', 'razon_social' => 'BOTICA DESTINO SAC', 'direccion' => 'AV. DESTINO 456'],
        ] as $cliente) {
            Cliente::updateOrCreate(
                ['empresa_id' => $empresa->id, 'numero_documento' => $cliente['numero_documento']],
                array_merge($cliente, ['tenant_id' => $tenant->id, 'empresa_id' => $empresa->id, 'estado' => true])
            );
        }
    }

    private function crearProductosDemo(Tenant $tenant, Empresa $empresa, Collection $tiendas): void
    {
        $categoria = Categoria::where('empresa_id', $empresa->id)->where('nombre', 'Analgesicos')->first();
        $marca = Marca::where('empresa_id', $empresa->id)->where('nombre', 'Generico')->first();
        $laboratorio = Laboratorio::where('empresa_id', $empresa->id)->where('nombre', 'Medifarma')->first();
        $accion = AccionTerapeutica::where('empresa_id', $empresa->id)->where('nombre', 'Analgesico')->first();
        $principioParacetamol = PrincipioActivo::where('empresa_id', $empresa->id)->where('nombre', 'Paracetamol')->first();
        $principioAmoxicilina = PrincipioActivo::where('empresa_id', $empresa->id)->where('nombre', 'Amoxicilina')->first();
        $afectacion = AfectacionIgv::where('codigo', '10')->first();
        $unidad = UnidadMedida::where('empresa_id', $empresa->id)->where('nombre', 'Unidad')->first();
        $caja = UnidadMedida::where('empresa_id', $empresa->id)->where('nombre', 'Caja')->first();

        if (! $categoria || ! $unidad || ! $caja) {
            return;
        }

        $paracetamol = Producto::updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_interno' => 'PROD000001'],
            [
                'tenant_id' => $tenant->id,
                'categoria_id' => $categoria->id,
                'marca_id' => $marca?->id,
                'laboratorio_id' => $laboratorio?->id,
                'principio_activo_id' => $principioParacetamol?->id,
                'accion_terapeutica_id' => $accion?->id,
                'afectacion_igv_id' => $afectacion?->id,
                'nombre' => 'Paracetamol 500mg',
                'descripcion' => 'Tableta analgesica y antipiretica',
                'concentracion' => '500mg',
                'requiere_receta' => false,
                'maneja_lote' => false,
                'maneja_vencimiento' => false,
                'afecto_igv' => true,
                'estado' => true,
            ]
        );

        $amoxicilina = Producto::updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_interno' => 'PROD000002'],
            [
                'tenant_id' => $tenant->id,
                'categoria_id' => Categoria::where('empresa_id', $empresa->id)->where('nombre', 'Antibioticos')->value('id') ?? $categoria->id,
                'marca_id' => $marca?->id,
                'laboratorio_id' => $laboratorio?->id,
                'principio_activo_id' => $principioAmoxicilina?->id,
                'accion_terapeutica_id' => AccionTerapeutica::where('empresa_id', $empresa->id)->where('nombre', 'Antibiotico')->value('id') ?? $accion?->id,
                'afectacion_igv_id' => $afectacion?->id,
                'nombre' => 'Amoxicilina 500mg',
                'descripcion' => 'Capsula antibiotica',
                'concentracion' => '500mg',
                'requiere_receta' => true,
                'maneja_lote' => true,
                'maneja_vencimiento' => true,
                'afecto_igv' => true,
                'estado' => true,
            ]
        );

        $this->syncPrincipios($paracetamol, [$principioParacetamol?->id]);
        $this->syncPrincipios($amoxicilina, [$principioAmoxicilina?->id]);

        $paracetamolUnidad = $this->crearPresentacion($tenant, $empresa, $paracetamol, $unidad, 'Unidad', '7750000000011', 1, 0.30, 1.00, true);
        $this->crearPresentacion($tenant, $empresa, $paracetamol, $caja, 'Caja x 50', '7750000000012', 50, 15.00, 48.00, false);
        $amoxicilinaUnidad = $this->crearPresentacion($tenant, $empresa, $amoxicilina, $unidad, 'Unidad', '7750000000021', 1, 0.80, 2.50, true);
        $this->crearPresentacion($tenant, $empresa, $amoxicilina, $caja, 'Caja x 100', '7750000000022', 100, 80.00, 220.00, false);

        foreach ($tiendas as $tienda) {
            $this->crearStock($tenant, $empresa, $tienda, $paracetamol, null, 500, 20, 2000);

            $lote = Lote::updateOrCreate(
                ['empresa_id' => $empresa->id, 'producto_id' => $amoxicilina->id, 'codigo_lote' => 'AMX-LOTE-001'],
                [
                    'tenant_id' => $tenant->id,
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'estado' => true,
                ]
            );

            $this->crearStock($tenant, $empresa, $tienda, $amoxicilina, $lote, 300, 10, 1000);
        }

        unset($paracetamolUnidad, $amoxicilinaUnidad);
    }

    private function crearPresentacion(Tenant $tenant, Empresa $empresa, Producto $producto, UnidadMedida $unidad, string $nombre, string $codigoBarra, float $factor, float $compra, float $venta, bool $principal): ProductoPresentacion
    {
        return ProductoPresentacion::updateOrCreate(
            ['empresa_id' => $empresa->id, 'codigo_barra' => $codigoBarra],
            [
                'tenant_id' => $tenant->id,
                'producto_id' => $producto->id,
                'unidad_medida_id' => $unidad->id,
                'nombre' => $nombre,
                'factor_conversion' => $factor,
                'precio_compra' => $compra,
                'precio_venta' => $venta,
                'es_principal' => $principal,
                'estado' => true,
            ]
        );
    }

    private function crearStock(Tenant $tenant, Empresa $empresa, Tienda $tienda, Producto $producto, ?Lote $lote, float $cantidad, float $minima, float $maxima): void
    {
        Stock::updateOrCreate(
            ['empresa_id' => $empresa->id, 'tienda_id' => $tienda->id, 'producto_id' => $producto->id, 'lote_id' => $lote?->id],
            [
                'tenant_id' => $tenant->id,
                'cantidad_actual' => $cantidad,
                'cantidad_minima' => $minima,
                'cantidad_maxima' => $maxima,
                'estado' => true,
            ]
        );
    }

    private function syncPrincipios(Producto $producto, array $principioIds): void
    {
        $principioIds = array_values(array_filter($principioIds));

        if (! Schema::hasTable('producto_principio_activo') || empty($principioIds)) {
            return;
        }

        $sync = [];
        foreach ($principioIds as $id) {
            $sync[$id] = ['tenant_id' => $producto->tenant_id, 'empresa_id' => $producto->empresa_id];
        }

        $producto->principiosActivos()->sync($sync);
    }

    private function crearPermisos(): Collection
    {
        $permissions = [
            'dashboard.ver' => 'Ver dashboard',
            'productos.ver' => 'Ver productos', 'productos.crear' => 'Crear productos', 'productos.editar' => 'Editar productos', 'productos.eliminar' => 'Eliminar productos',
            'categorias.ver' => 'Ver categorias', 'categorias.crear' => 'Crear categorias', 'categorias.editar' => 'Editar categorias', 'categorias.eliminar' => 'Eliminar categorias',
            'marcas.ver' => 'Ver marcas', 'marcas.crear' => 'Crear marcas', 'marcas.editar' => 'Editar marcas', 'marcas.eliminar' => 'Eliminar marcas',
            'laboratorios.ver' => 'Ver laboratorios', 'laboratorios.crear' => 'Crear laboratorios', 'laboratorios.editar' => 'Editar laboratorios', 'laboratorios.eliminar' => 'Eliminar laboratorios',
            'principios_activos.ver' => 'Ver principios activos', 'principios_activos.crear' => 'Crear principios activos', 'principios_activos.editar' => 'Editar principios activos', 'principios_activos.eliminar' => 'Eliminar principios activos',
            'acciones_terapeuticas.ver' => 'Ver acciones terapeuticas', 'acciones_terapeuticas.crear' => 'Crear acciones terapeuticas', 'acciones_terapeuticas.editar' => 'Editar acciones terapeuticas', 'acciones_terapeuticas.eliminar' => 'Eliminar acciones terapeuticas',
            'unidades_medida.ver' => 'Ver unidades de medida', 'unidades_medida.crear' => 'Crear unidades de medida', 'unidades_medida.editar' => 'Editar unidades de medida', 'unidades_medida.eliminar' => 'Eliminar unidades de medida',
            'ventas.ver' => 'Ver ventas', 'ventas.crear' => 'Crear ventas', 'ventas.imprimir' => 'Imprimir ventas', 'ventas.exportar' => 'Exportar ventas',
            'caja.ver' => 'Ver caja', 'caja.aperturar' => 'Aperturar caja', 'caja.cerrar' => 'Cerrar caja', 'caja.ingreso' => 'Registrar ingresos de caja', 'caja.egreso' => 'Registrar egresos de caja', 'caja.historial' => 'Ver historial de caja',
            'compras.ver' => 'Ver compras', 'compras.crear' => 'Crear compras', 'compras.anular' => 'Anular compras', 'compras.pdf.ver' => 'Ver PDF compras', 'proveedores.ver' => 'Ver proveedores', 'proveedores.crear' => 'Crear proveedores', 'proveedores.editar' => 'Editar proveedores', 'proveedores.eliminar' => 'Eliminar proveedores',
            'inventario.ver' => 'Ver inventario', 'inventario.entrada' => 'Entrada de inventario', 'inventario.salida' => 'Salida de inventario', 'inventario.ajuste' => 'Ajuste de inventario', 'inventario.kardex' => 'Ver kardex',
            'lotes.ver' => 'Ver lotes', 'lotes.crear' => 'Crear lotes', 'lotes.editar' => 'Editar lotes', 'lotes.eliminar' => 'Eliminar lotes',
            'reportes.ver' => 'Ver reportes',
            'sunat.ver' => 'Ver SUNAT', 'sunat.configuracion.ver' => 'Ver configuracion SUNAT', 'sunat.configuracion.crear' => 'Crear configuracion SUNAT', 'sunat.configuracion.editar' => 'Editar configuracion SUNAT', 'sunat.configuracion.eliminar' => 'Eliminar configuracion SUNAT',
            'sunat.comprobantes.ver' => 'Ver comprobantes electronicos', 'sunat.comprobantes.emitir' => 'Emitir comprobantes electronicos', 'sunat.comprobantes.reenviar' => 'Reenviar comprobantes electronicos', 'sunat.documentos.descargar' => 'Descargar documentos SUNAT',
            'sunat.notas.ver' => 'Ver notas electronicas', 'sunat.notas.crear' => 'Crear notas electronicas',
            'notas_credito.ver' => 'Ver notas de credito', 'notas_credito.crear' => 'Crear notas de credito', 'notas_credito.enviar_sunat' => 'Enviar notas de credito', 'notas_credito.reenviar_sunat' => 'Reenviar notas de credito', 'notas_credito.descargar_xml' => 'Descargar XML de notas de credito', 'notas_credito.descargar_cdr' => 'Descargar CDR de notas de credito', 'notas_credito.xml.descargar' => 'Descargar XML de notas de credito', 'notas_credito.cdr.descargar' => 'Descargar CDR de notas de credito', 'notas_credito.pdf.generar' => 'Generar PDF de notas de credito', 'notas_credito.pdf.descargar' => 'Descargar PDF de notas de credito', 'notas_credito.ticket.generar' => 'Generar ticket de notas de credito', 'notas_credito.ticket.descargar' => 'Descargar ticket de notas de credito',
            'notas_debito.ver' => 'Ver notas de debito', 'notas_debito.crear' => 'Crear notas de debito', 'notas_debito.enviar_sunat' => 'Enviar notas de debito', 'notas_debito.reenviar_sunat' => 'Reenviar notas de debito', 'notas_debito.descargar_xml' => 'Descargar XML de notas de debito', 'notas_debito.descargar_cdr' => 'Descargar CDR de notas de debito', 'notas_debito.xml.descargar' => 'Descargar XML de notas de debito', 'notas_debito.cdr.descargar' => 'Descargar CDR de notas de debito', 'notas_debito.pdf.generar' => 'Generar PDF de notas de debito', 'notas_debito.pdf.descargar' => 'Descargar PDF de notas de debito', 'notas_debito.ticket.generar' => 'Generar ticket de notas de debito', 'notas_debito.ticket.descargar' => 'Descargar ticket de notas de debito',
            'sunat.resumenes.ver' => 'Ver resumenes diarios', 'sunat.resumenes.generar' => 'Generar resumenes diarios', 'sunat.bajas.ver' => 'Ver comunicaciones de baja', 'sunat.bajas.generar' => 'Generar comunicaciones de baja',
            'sunat.guias.ver' => 'Ver guias de remision', 'sunat.guias.crear' => 'Crear guias de remision',
            'guias.crear' => 'Crear guias de remision', 'guias.enviar_sunat' => 'Enviar guias a SUNAT', 'guias.reenviar_sunat' => 'Reenviar guias a SUNAT', 'guias.descargar_xml' => 'Descargar XML de guias', 'guias.descargar_cdr' => 'Descargar CDR de guias', 'guias.pdf.generar' => 'Generar PDF de guias', 'guias.pdf.descargar' => 'Descargar PDF de guias', 'guias.ticket.generar' => 'Generar ticket de guias', 'guias.ticket.descargar' => 'Descargar ticket de guias',
            'comprobantes.notas_venta.ver' => 'Ver notas de venta',
            'comprobantes.ver' => 'Ver modulo comprobantes',
            'configuracion.empresa.ver' => 'Ver configuracion de empresa', 'configuracion.empresa.editar' => 'Editar configuracion de empresa',
            'tiendas.ver' => 'Ver tiendas', 'tiendas.crear' => 'Crear tiendas', 'tiendas.editar' => 'Editar tiendas', 'tiendas.eliminar' => 'Eliminar tiendas',
            'usuarios.ver' => 'Ver usuarios', 'usuarios.crear' => 'Crear usuarios', 'usuarios.editar' => 'Editar usuarios', 'usuarios.eliminar' => 'Eliminar usuarios',
            'roles.ver' => 'Ver roles y permisos', 'roles.crear' => 'Crear roles', 'roles.editar' => 'Editar roles', 'roles.eliminar' => 'Eliminar roles',
            'series.ver' => 'Ver series', 'series.crear' => 'Crear series', 'series.editar' => 'Editar series', 'series.eliminar' => 'Eliminar series',
        ];

        return collect($permissions)->map(function (string $label, string $name) {
            return Permission::updateOrCreate(
                ['name' => $name],
                ['label' => $label, 'description' => $label, 'active' => true]
            );
        });
    }

    private function crearRoles(Empresa $empresa, Collection $permissions): array
    {
        $roles = [
            'Administrador' => $permissions->pluck('id')->all(),
            'Supervisor' => $this->permissionIds([
                'dashboard.ver', 'productos.ver', 'ventas.ver', 'ventas.imprimir', 'ventas.exportar', 'caja.ver', 'caja.historial', 'compras.ver', 'compras.anular', 'compras.pdf.ver', 'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'reportes.ver',
                'sunat.ver', 'sunat.configuracion.ver', 'sunat.comprobantes.ver', 'sunat.documentos.descargar', 'sunat.notas.ver', 'sunat.notas.crear',
                'notas_credito.ver', 'notas_credito.crear', 'notas_debito.ver', 'notas_debito.crear',
                'sunat.resumenes.ver', 'sunat.bajas.ver', 'sunat.guias.ver', 'sunat.guias.crear',
                'guias.crear', 'guias.enviar_sunat', 'guias.reenviar_sunat', 'guias.descargar_xml', 'guias.descargar_cdr', 'guias.pdf.generar', 'guias.pdf.descargar', 'guias.ticket.generar', 'guias.ticket.descargar',
                'configuracion.empresa.ver', 'configuracion.empresa.editar', 'tiendas.ver', 'tiendas.crear', 'tiendas.editar', 'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'roles.ver', 'roles.crear', 'roles.editar', 'series.ver', 'series.crear', 'series.editar',
                'inventario.ver', 'inventario.entrada', 'inventario.salida', 'inventario.ajuste', 'inventario.kardex', 'lotes.ver', 'lotes.crear', 'lotes.editar', 'lotes.eliminar',
                'categorias.ver', 'marcas.ver', 'laboratorios.ver', 'principios_activos.ver', 'acciones_terapeuticas.ver', 'unidades_medida.ver',
            ]),
            'Cajero' => $this->permissionIds([
                'dashboard.ver', 'ventas.ver', 'ventas.crear', 'ventas.imprimir', 'ventas.exportar', 'caja.ver', 'caja.aperturar', 'caja.cerrar', 'caja.ingreso', 'caja.egreso',
                'sunat.comprobantes.ver', 'sunat.documentos.descargar', 'sunat.notas.ver', 'notas_credito.ver', 'notas_debito.ver', 'inventario.ver', 'lotes.ver',
            ]),
            'Almacenero' => $this->permissionIds([
                'dashboard.ver', 'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar', 'compras.ver', 'compras.crear', 'compras.anular', 'compras.pdf.ver', 'proveedores.ver', 'proveedores.crear', 'proveedores.editar',
                'inventario.ver', 'inventario.entrada', 'inventario.salida', 'inventario.ajuste', 'inventario.kardex', 'lotes.ver', 'lotes.crear', 'lotes.editar', 'lotes.eliminar',
                'categorias.ver', 'categorias.crear', 'categorias.editar', 'marcas.ver', 'marcas.crear', 'marcas.editar', 'laboratorios.ver', 'laboratorios.crear', 'laboratorios.editar',
                'principios_activos.ver', 'principios_activos.crear', 'principios_activos.editar', 'acciones_terapeuticas.ver', 'acciones_terapeuticas.crear', 'acciones_terapeuticas.editar', 'unidades_medida.ver', 'unidades_medida.crear', 'unidades_medida.editar',
            ]),
        ];

        $created = [];
        foreach ($roles as $name => $permissionIds) {
            $role = Role::updateOrCreate(
                ['empresa_id' => $empresa->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'description' => "Rol {$name}", 'active' => true]
            );
            $role->permissions()->sync($permissionIds);
            $created[$name] = $role;
        }

        return $created;
    }

    private function crearUsuarios(Tenant $tenant, Empresa $empresa, Collection $tiendas, array $roles): void
    {
        $users = [
            ['name' => 'Administrador Demo', 'email' => 'admin@botica.demo', 'role' => 'Administrador', 'tiendas' => $tiendas],
            ['name' => 'Cajero Demo', 'email' => 'cajero@botica.demo', 'role' => 'Cajero', 'tiendas' => $tiendas->take(2)],
            ['name' => 'Almacenero Demo', 'email' => 'almacen@botica.demo', 'role' => 'Almacenero', 'tiendas' => $tiendas],
            ['name' => 'Supervisor Demo', 'email' => 'supervisor@botica.demo', 'role' => 'Supervisor', 'tiendas' => $tiendas],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresa->id,
                    'tienda_activa_id' => $data['tiendas']->first()?->id,
                    'role_id' => $roles[$data['role']]->id,
                    'name' => $data['name'],
                    'password' => Hash::make('password123'),
                    'estado' => true,
                ]
            );

            $this->asignarTiendas($user, $data['tiendas'], $tenant->id, $empresa->id);
        }
    }

    private function syncAdminAllPermissions(Empresa $empresa): void
    {
        $allPermissionIds = Permission::pluck('id')->all();

        Role::where('empresa_id', $empresa->id)
            ->whereIn('name', ['Administrador'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->sync($allPermissionIds));
    }

    private function permissionIds(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('id')->all();
    }

    private function asignarTiendas(User $user, iterable $tiendas, int $tenantId, int $empresaId): void
    {
        foreach ($tiendas as $tienda) {
            $user->tiendas()->syncWithoutDetaching([
                $tienda->id => [
                    'tenant_id' => $tenantId,
                    'empresa_id' => $empresaId,
                    'estado' => true,
                ],
            ]);
        }
    }
}
