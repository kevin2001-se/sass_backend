<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\AccionTerapeutica;
use App\Models\Categoria;
use App\Models\Laboratorio;
use App\Models\Marca;
use App\Models\Permission;
use App\Models\PrincipioActivo;
use App\Models\ProductoConfiguracion;
use App\Models\Role;
use App\Models\SerieComprobante;
use App\Models\Tienda;
use App\Models\Tenant;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MotivoTrasladoSeeder::class,
            ModalidadTransporteSeeder::class,
            UnidadMedidaSunatSeeder::class,
            UbigeoSeeder::class,
        ]);
        $tenant = Tenant::create([
            'name' => 'Demo Tenant',
            'slug' => 'demo-tenant',
            'active' => true,
        ]);

        $empresa = Empresa::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Botica Demo',
            'ruc' => '20456789012',
            'direccion' => 'Av. Peru 123, Lima',
            'active' => true,
        ]);

        $tiendas = $this->crearTiendas($tenant, $empresa);
        $tiendaPrincipal = $tiendas->first();

        $this->crearSeriesComprobantes($tenant, $empresa, $tiendas);
        $this->crearCatalogosDemo($tenant, $empresa);
        $this->crearProductoConfiguracion($tenant, $empresa);

        $permissions = $this->crearPermisos();
        $roles = $this->crearRoles($empresa, $permissions);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'tienda_activa_id' => $tiendaPrincipal->id,
            'role_id' => $roles['Administrador']->id,
            'name' => 'Administrador Demo',
            'email' => 'admin@botica.demo',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->asignarTiendas($admin, $tiendas, $tenant->id, $empresa->id);

        $cajero = User::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'tienda_activa_id' => null,
            'role_id' => $roles['Cajero']->id,
            'name' => 'Cajero Multi Tienda',
            'email' => 'cajero@botica.demo',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->asignarTiendas($cajero, $tiendas->take(2), $tenant->id, $empresa->id);

        $almacenero = User::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'tienda_activa_id' => null,
            'role_id' => $roles['Almacenero']->id,
            'name' => 'Almacenero Multi Tienda',
            'email' => 'almacen@botica.demo',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->asignarTiendas($almacenero, $tiendas, $tenant->id, $empresa->id);

        $supervisor = User::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'tienda_activa_id' => $tiendaPrincipal->id,
            'role_id' => $roles['Supervisor']->id,
            'name' => 'Supervisor Demo',
            'email' => 'supervisor@botica.demo',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->asignarTiendas($supervisor, $tiendas, $tenant->id, $empresa->id);
    }

    private function crearTiendas(Tenant $tenant, Empresa $empresa): Collection
    {
        return collect([
            [
                'nombre' => 'Botica Principal',
                'codigo' => 'BOT-001',
                'direccion' => 'Av. Peru 123, Lima',
                'ubigeo' => '150101',
            ],
            [
                'nombre' => 'Botica Norte',
                'codigo' => 'BOT-002',
                'direccion' => 'Av. Los Olivos 456, Lima',
                'ubigeo' => '150117',
            ],
            [
                'nombre' => 'Botica Sur',
                'codigo' => 'BOT-003',
                'direccion' => 'Av. San Juan 789, Lima',
                'ubigeo' => '150132',
            ],
        ])->map(fn (array $tienda) => Tienda::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'nombre' => $tienda['nombre'],
            'codigo' => $tienda['codigo'],
            'direccion' => $tienda['direccion'],
            'ubigeo' => $tienda['ubigeo'] ?? null,
            'estado' => true,
        ]));
    }

    private function crearSeriesComprobantes(Tenant $tenant, Empresa $empresa, Collection $tiendas): void
    {
        $series = [
            ['tipo_comprobante' => 'NOTA_VENTA', 'serie' => 'NV01'],
            ['tipo_comprobante' => 'BOLETA', 'serie' => 'B001'],
            ['tipo_comprobante' => 'FACTURA', 'serie' => 'F001'],
            ['tipo_comprobante' => 'NOTA_CREDITO', 'serie' => 'FC01'],
            ['tipo_comprobante' => 'NOTA_DEBITO', 'serie' => 'FD01'],
            ['tipo_comprobante' => 'GUIA_REMISION', 'serie' => 'T001'],
        ];

        $tiendas->each(function (Tienda $tienda) use ($tenant, $empresa, $series) {
            foreach ($series as $serie) {
                SerieComprobante::create([
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresa->id,
                    'tienda_id' => $tienda->id,
                    'tipo_comprobante' => $serie['tipo_comprobante'],
                    'serie' => $serie['serie'],
                    'correlativo_actual' => 0,
                    'estado' => true,
                ]);
            }
        });
    }

    private function crearCatalogosDemo(Tenant $tenant, Empresa $empresa): void
    {
        $base = [
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'estado' => true,
        ];

        foreach ([
            ['nombre' => 'AnalgÃ©sicos', 'descripcion' => 'Medicamentos para aliviar el dolor.'],
            ['nombre' => 'AntibiÃ³ticos', 'descripcion' => 'Medicamentos antimicrobianos bajo control.'],
            ['nombre' => 'Antigripales', 'descripcion' => 'Productos para sÃ­ntomas de resfrÃ­o y gripe.'],
            ['nombre' => 'Gastrointestinales', 'descripcion' => 'Tratamientos para el sistema digestivo.'],
            ['nombre' => 'DermatolÃ³gicos', 'descripcion' => 'Cremas, ungÃ¼entos y tratamientos de piel.'],
            ['nombre' => 'Vitaminas y suplementos', 'descripcion' => 'Suplementos nutricionales y vitamÃ­nicos.'],
            ['nombre' => 'Cuidado personal', 'descripcion' => 'Productos de higiene y cuidado diario.'],
            ['nombre' => 'Material mÃ©dico', 'descripcion' => 'Insumos y dispositivos de uso mÃ©dico.'],
        ] as $item) {
            Categoria::create(array_merge($base, $item));
        }

        foreach ([
            ['nombre' => 'GenÃ©rico', 'descripcion' => 'Producto sin marca comercial especÃ­fica.'],
            ['nombre' => 'Panadol', 'descripcion' => 'Marca comercial de analgÃ©sicos.'],
            ['nombre' => 'Apronax', 'descripcion' => 'Marca comercial de antiinflamatorios.'],
            ['nombre' => 'Dolocordralan', 'descripcion' => 'Marca comercial farmacÃ©utica.'],
            ['nombre' => 'Bismutol', 'descripcion' => 'Marca comercial gastrointestinal.'],
            ['nombre' => 'Vick', 'descripcion' => 'Marca de productos respiratorios.'],
            ['nombre' => 'Ensure', 'descripcion' => 'Marca de suplementos nutricionales.'],
            ['nombre' => 'Nivea', 'descripcion' => 'Marca de cuidado personal.'],
        ] as $item) {
            Marca::create(array_merge($base, $item));
        }

        foreach ([
            ['nombre' => 'Medifarma', 'descripcion' => 'Laboratorio farmacÃ©utico peruano.'],
            ['nombre' => 'Portugal', 'descripcion' => 'Laboratorio de productos farmacÃ©uticos.'],
            ['nombre' => 'Hersil', 'descripcion' => 'Laboratorio peruano de medicamentos.'],
            ['nombre' => 'Farmindustria', 'descripcion' => 'Laboratorio farmacÃ©utico nacional.'],
            ['nombre' => 'Bayer', 'descripcion' => 'Laboratorio multinacional.'],
            ['nombre' => 'Pfizer', 'descripcion' => 'Laboratorio multinacional.'],
            ['nombre' => 'GlaxoSmithKline', 'descripcion' => 'Laboratorio multinacional.'],
            ['nombre' => 'Abbott', 'descripcion' => 'Laboratorio de medicamentos y nutriciÃ³n.'],
        ] as $item) {
            Laboratorio::create(array_merge($base, $item));
        }

        foreach ([
            ['nombre' => 'Paracetamol', 'descripcion' => 'AnalgÃ©sico y antipirÃ©tico.'],
            ['nombre' => 'Ibuprofeno', 'descripcion' => 'Antiinflamatorio no esteroideo.'],
            ['nombre' => 'Naproxeno', 'descripcion' => 'Antiinflamatorio y analgÃ©sico.'],
            ['nombre' => 'Amoxicilina', 'descripcion' => 'AntibiÃ³tico betalactÃ¡mico.'],
            ['nombre' => 'Azitromicina', 'descripcion' => 'AntibiÃ³tico macrÃ³lido.'],
            ['nombre' => 'Loratadina', 'descripcion' => 'AntihistamÃ­nico.'],
            ['nombre' => 'Omeprazol', 'descripcion' => 'Inhibidor de bomba de protones.'],
            ['nombre' => 'Clotrimazol', 'descripcion' => 'AntimicÃ³tico.'],
        ] as $item) {
            PrincipioActivo::create(array_merge($base, $item));
        }

        foreach ([
            ['nombre' => 'AnalgÃ©sico', 'descripcion' => 'Alivio del dolor.'],
            ['nombre' => 'AntipirÃ©tico', 'descripcion' => 'ReducciÃ³n de fiebre.'],
            ['nombre' => 'Antiinflamatorio', 'descripcion' => 'ReducciÃ³n de inflamaciÃ³n.'],
            ['nombre' => 'Antibacteriano', 'descripcion' => 'Tratamiento de infecciones bacterianas.'],
            ['nombre' => 'AntihistamÃ­nico', 'descripcion' => 'Alivio de alergias.'],
            ['nombre' => 'AntiÃ¡cido', 'descripcion' => 'Tratamiento de acidez gÃ¡strica.'],
            ['nombre' => 'AntimicÃ³tico', 'descripcion' => 'Tratamiento de hongos.'],
            ['nombre' => 'Suplemento nutricional', 'descripcion' => 'Apoyo nutricional y vitamÃ­nico.'],
        ] as $item) {
            AccionTerapeutica::create(array_merge($base, $item));
        }

        foreach ([
            ['nombre' => 'Unidad', 'abreviatura' => 'UND'],
            ['nombre' => 'Caja', 'abreviatura' => 'CAJ'],
            ['nombre' => 'BlÃ­ster', 'abreviatura' => 'BLI'],
            ['nombre' => 'Frasco', 'abreviatura' => 'FCO'],
            ['nombre' => 'Tubo', 'abreviatura' => 'TUB'],
            ['nombre' => 'Sobre', 'abreviatura' => 'SOB'],
            ['nombre' => 'Ampolla', 'abreviatura' => 'AMP'],
            ['nombre' => 'Tableta', 'abreviatura' => 'TAB'],
        ] as $item) {
            UnidadMedida::create(array_merge($base, $item));
        }
    }

    private function crearProductoConfiguracion(Tenant $tenant, Empresa $empresa): void
    {
        ProductoConfiguracion::create([
            'tenant_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'autogenerar_codigo_interno' => true,
            'prefijo_codigo_interno' => 'PROD',
            'ultimo_correlativo_codigo_interno' => 0,
            'autogenerar_codigo_barra' => true,
            'prefijo_codigo_barra' => 'BOT',
            'ultimo_correlativo_codigo_barra' => 0,
            'estado' => true,
        ]);
    }

    private function crearPermisos(): Collection
    {
        return collect([
            ['name' => 'dashboard.ver', 'label' => 'Ver dashboard', 'description' => 'Acceder al panel principal'],

            ['name' => 'productos.ver', 'label' => 'Ver productos', 'description' => 'Visualizar listado de productos'],
            ['name' => 'productos.crear', 'label' => 'Crear productos', 'description' => 'Registrar productos nuevos'],
            ['name' => 'productos.editar', 'label' => 'Editar productos', 'description' => 'Modificar datos de productos'],
            ['name' => 'productos.eliminar', 'label' => 'Eliminar productos', 'description' => 'Eliminar productos del catalogo'],

            ['name' => 'categorias.ver', 'label' => 'Ver categorias', 'description' => 'Visualizar categorias'],
            ['name' => 'categorias.crear', 'label' => 'Crear categorias', 'description' => 'Registrar categorias'],
            ['name' => 'categorias.editar', 'label' => 'Editar categorias', 'description' => 'Modificar categorias'],
            ['name' => 'categorias.eliminar', 'label' => 'Eliminar categorias', 'description' => 'Desactivar categorias'],

            ['name' => 'marcas.ver', 'label' => 'Ver marcas', 'description' => 'Visualizar marcas'],
            ['name' => 'marcas.crear', 'label' => 'Crear marcas', 'description' => 'Registrar marcas'],
            ['name' => 'marcas.editar', 'label' => 'Editar marcas', 'description' => 'Modificar marcas'],
            ['name' => 'marcas.eliminar', 'label' => 'Eliminar marcas', 'description' => 'Desactivar marcas'],

            ['name' => 'laboratorios.ver', 'label' => 'Ver laboratorios', 'description' => 'Visualizar laboratorios'],
            ['name' => 'laboratorios.crear', 'label' => 'Crear laboratorios', 'description' => 'Registrar laboratorios'],
            ['name' => 'laboratorios.editar', 'label' => 'Editar laboratorios', 'description' => 'Modificar laboratorios'],
            ['name' => 'laboratorios.eliminar', 'label' => 'Eliminar laboratorios', 'description' => 'Desactivar laboratorios'],

            ['name' => 'principios_activos.ver', 'label' => 'Ver principios activos', 'description' => 'Visualizar principios activos'],
            ['name' => 'principios_activos.crear', 'label' => 'Crear principios activos', 'description' => 'Registrar principios activos'],
            ['name' => 'principios_activos.editar', 'label' => 'Editar principios activos', 'description' => 'Modificar principios activos'],
            ['name' => 'principios_activos.eliminar', 'label' => 'Eliminar principios activos', 'description' => 'Desactivar principios activos'],

            ['name' => 'acciones_terapeuticas.ver', 'label' => 'Ver acciones terapeuticas', 'description' => 'Visualizar acciones terapeuticas'],
            ['name' => 'acciones_terapeuticas.crear', 'label' => 'Crear acciones terapeuticas', 'description' => 'Registrar acciones terapeuticas'],
            ['name' => 'acciones_terapeuticas.editar', 'label' => 'Editar acciones terapeuticas', 'description' => 'Modificar acciones terapeuticas'],
            ['name' => 'acciones_terapeuticas.eliminar', 'label' => 'Eliminar acciones terapeuticas', 'description' => 'Desactivar acciones terapeuticas'],

            ['name' => 'unidades_medida.ver', 'label' => 'Ver unidades de medida', 'description' => 'Visualizar unidades de medida'],
            ['name' => 'unidades_medida.crear', 'label' => 'Crear unidades de medida', 'description' => 'Registrar unidades de medida'],
            ['name' => 'unidades_medida.editar', 'label' => 'Editar unidades de medida', 'description' => 'Modificar unidades de medida'],
            ['name' => 'unidades_medida.eliminar', 'label' => 'Eliminar unidades de medida', 'description' => 'Desactivar unidades de medida'],

            ['name' => 'ventas.ver', 'label' => 'Ver ventas', 'description' => 'Visualizar operaciones de venta'],
            ['name' => 'ventas.crear', 'label' => 'Crear ventas', 'description' => 'Registrar ventas en el sistema'],
            ['name' => 'ventas.imprimir', 'label' => 'Imprimir ventas', 'description' => 'Imprimir tickets de venta'],
            ['name' => 'ventas.exportar', 'label' => 'Exportar ventas', 'description' => 'Descargar PDF de ventas'],
            ['name' => 'guias.crear', 'label' => 'Crear guias de remision', 'description' => 'Crear guias de remision desde ventas o manualmente'],
            ['name' => 'guias.enviar_sunat', 'label' => 'Enviar guias a SUNAT', 'description' => 'Enviar guias de remision a SUNAT'],
            ['name' => 'guias.reenviar_sunat', 'label' => 'Reenviar guias a SUNAT', 'description' => 'Reintentar envio SUNAT de guias'],
            ['name' => 'guias.descargar_xml', 'label' => 'Descargar XML de guias', 'description' => 'Descargar XML privado de guias'],
            ['name' => 'guias.descargar_cdr', 'label' => 'Descargar CDR de guias', 'description' => 'Descargar CDR privado de guias'],
            ['name' => 'guias.pdf.generar', 'label' => 'Generar PDF de guias', 'description' => 'Generar PDF A4 privado de guias de remision'],
            ['name' => 'guias.pdf.descargar', 'label' => 'Descargar PDF de guias', 'description' => 'Descargar PDF A4 privado de guias de remision'],
            ['name' => 'guias.ticket.generar', 'label' => 'Generar ticket de guias', 'description' => 'Generar ticket 80mm privado de guias de remision'],
            ['name' => 'guias.ticket.descargar', 'label' => 'Descargar ticket de guias', 'description' => 'Descargar ticket 80mm privado de guias de remision'],
            ['name' => 'caja.ver', 'label' => 'Ver caja', 'description' => 'Acceder al estado de caja'],
            ['name' => 'caja.aperturar', 'label' => 'Aperturar caja', 'description' => 'Abrir caja para el dia'],
            ['name' => 'caja.cerrar', 'label' => 'Cerrar caja', 'description' => 'Cerrar caja al final del turno'],
            ['name' => 'caja.ingreso', 'label' => 'Registrar ingresos de caja', 'description' => 'Registrar ingresos manuales en caja'],
            ['name' => 'caja.egreso', 'label' => 'Registrar egresos de caja', 'description' => 'Registrar egresos manuales en caja'],
            ['name' => 'caja.historial', 'label' => 'Ver historial de caja', 'description' => 'Consultar historial de aperturas y cierres'],

            ['name' => 'compras.ver', 'label' => 'Ver compras', 'description' => 'Visualizar compras y ordenes'],
            ['name' => 'compras.crear', 'label' => 'Crear compras', 'description' => 'Registrar compras de insumos'],

            ['name' => 'inventario.ver', 'label' => 'Ver inventario', 'description' => 'Consultar stock actual'],
            ['name' => 'inventario.entrada', 'label' => 'Entrada de inventario', 'description' => 'Registrar entradas de stock'],
            ['name' => 'inventario.salida', 'label' => 'Salida de inventario', 'description' => 'Registrar salidas de stock'],
            ['name' => 'inventario.ajuste', 'label' => 'Ajuste de inventario', 'description' => 'Registrar ajustes de stock'],
            ['name' => 'inventario.kardex', 'label' => 'Ver kardex', 'description' => 'Consultar kardex de productos'],
            ['name' => 'lotes.ver', 'label' => 'Ver lotes', 'description' => 'Visualizar lotes'],
            ['name' => 'lotes.crear', 'label' => 'Crear lotes', 'description' => 'Registrar lotes'],
            ['name' => 'lotes.editar', 'label' => 'Editar lotes', 'description' => 'Modificar lotes'],
            ['name' => 'lotes.eliminar', 'label' => 'Eliminar lotes', 'description' => 'Desactivar lotes'],
            ['name' => 'reportes.ver', 'label' => 'Ver reportes', 'description' => 'Acceder a reportes del negocio'],
            ['name' => 'sunat.ver', 'label' => 'Ver SUNAT', 'description' => 'Acceder a documentos y configuracion SUNAT'],
            ['name' => 'sunat.configuracion.ver', 'label' => 'Ver configuracion SUNAT', 'description' => 'Consultar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.crear', 'label' => 'Crear configuracion SUNAT', 'description' => 'Registrar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.editar', 'label' => 'Editar configuracion SUNAT', 'description' => 'Actualizar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.configuracion.eliminar', 'label' => 'Eliminar configuracion SUNAT', 'description' => 'Desactivar configuracion SUNAT de la empresa'],
            ['name' => 'sunat.comprobantes.ver', 'label' => 'Ver comprobantes electronicos', 'description' => 'Consultar boletas, facturas y comprobantes electronicos'],
            ['name' => 'sunat.comprobantes.emitir', 'label' => 'Emitir comprobantes electronicos', 'description' => 'Enviar comprobantes electronicos a SUNAT'],
            ['name' => 'sunat.comprobantes.reenviar', 'label' => 'Reenviar comprobantes electronicos', 'description' => 'Reintentar envio de comprobantes electronicos'],
            ['name' => 'sunat.documentos.descargar', 'label' => 'Descargar documentos SUNAT', 'description' => 'Descargar PDF, tickets, XML y CDR'],
            ['name' => 'sunat.notas.ver', 'label' => 'Ver notas electronicas', 'description' => 'Consultar notas de credito y debito'],
            ['name' => 'sunat.notas.crear', 'label' => 'Crear notas electronicas', 'description' => 'Generar notas de credito y debito'],
            ['name' => 'sunat.resumenes.ver', 'label' => 'Ver resumenes diarios', 'description' => 'Consultar resumenes diarios de boletas'],
            ['name' => 'sunat.resumenes.generar', 'label' => 'Generar resumenes diarios', 'description' => 'Generar, enviar y consultar resumenes diarios'],
            ['name' => 'sunat.bajas.ver', 'label' => 'Ver comunicaciones de baja', 'description' => 'Consultar comunicaciones de baja'],
            ['name' => 'sunat.bajas.generar', 'label' => 'Generar comunicaciones de baja', 'description' => 'Generar, enviar y consultar comunicaciones de baja'],
            ['name' => 'sunat.guias.ver', 'label' => 'Ver guias de remision', 'description' => 'Consultar guias de remision electronicas'],
            ['name' => 'sunat.guias.crear', 'label' => 'Crear guias de remision', 'description' => 'Generar y enviar guias de remision electronicas'],
        ])->map(fn (array $permission) => Permission::updateOrCreate(
            ['name' => $permission['name']],
            array_merge($permission, ['active' => true])
        ));
    }

    private function crearRoles(Empresa $empresa, Collection $permissions): array
    {
        $catalogoLectura = [
            'categorias.ver',
            'marcas.ver',
            'laboratorios.ver',
            'principios_activos.ver',
            'acciones_terapeuticas.ver',
            'unidades_medida.ver',
        ];

        $catalogoEscritura = [
            ...$catalogoLectura,
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',
            'marcas.crear',
            'marcas.editar',
            'marcas.eliminar',
            'laboratorios.crear',
            'laboratorios.editar',
            'laboratorios.eliminar',
            'principios_activos.crear',
            'principios_activos.editar',
            'principios_activos.eliminar',
            'acciones_terapeuticas.crear',
            'acciones_terapeuticas.editar',
            'acciones_terapeuticas.eliminar',
            'unidades_medida.crear',
            'unidades_medida.editar',
            'unidades_medida.eliminar',
        ];

        $inventarioLectura = [
            'inventario.ver',
            'inventario.kardex',
            'lotes.ver',
        ];

        $inventarioOperacion = [
            ...$inventarioLectura,
            'inventario.entrada',
            'inventario.salida',
            'inventario.ajuste',
            'lotes.crear',
            'lotes.editar',
            'lotes.eliminar',
        ];

        $sunatLectura = [
            'sunat.ver',
            'sunat.configuracion.ver',
            'sunat.comprobantes.ver',
            'sunat.documentos.descargar',
            'sunat.notas.ver',
            'sunat.resumenes.ver',
            'sunat.bajas.ver',
            'sunat.guias.ver',
        ];

        $sunatOperacion = [
            ...$sunatLectura,
            'sunat.configuracion.crear',
            'sunat.configuracion.editar',
            'sunat.configuracion.eliminar',
            'sunat.comprobantes.emitir',
            'sunat.comprobantes.reenviar',
            'sunat.notas.crear',
            'sunat.resumenes.generar',
            'sunat.bajas.generar',
            'sunat.guias.crear',
        ];

        $roles = [
            'Administrador' => $permissions->pluck('id')->all(),
            'Cajero' => $this->permissionIds([
                'dashboard.ver',
                'ventas.ver',
                'ventas.crear',
                'ventas.imprimir',
                'ventas.exportar',
                'guias.crear',
                'guias.enviar_sunat',
                'guias.reenviar_sunat',
                'guias.descargar_xml',
                'guias.descargar_cdr',
                'guias.pdf.generar',
                'guias.pdf.descargar',
                'guias.ticket.generar',
                'guias.ticket.descargar',
                'caja.ver',
                'caja.aperturar',
                'caja.cerrar',
                'caja.ingreso',
                'caja.egreso',
                'caja.historial',
                'sunat.comprobantes.ver',
                'sunat.documentos.descargar',
                ...$inventarioLectura,
            ]),
            'Almacenero' => $this->permissionIds([
                'dashboard.ver',
                'productos.ver',
                'productos.crear',
                'productos.editar',
                'productos.eliminar',
                'compras.ver',
                'compras.crear',
                ...$inventarioOperacion,
                ...$catalogoEscritura,
            ]),
            'Supervisor' => $this->permissionIds([
                'dashboard.ver',
                'productos.ver',
                'ventas.ver',
                'ventas.imprimir',
                'ventas.exportar',
                'guias.crear',
                'guias.enviar_sunat',
                'guias.reenviar_sunat',
                'guias.descargar_xml',
                'guias.descargar_cdr',
                'guias.pdf.generar',
                'guias.pdf.descargar',
                'guias.ticket.generar',
                'guias.ticket.descargar',
                'caja.ver',
                'caja.historial',
                'compras.ver',
                'reportes.ver',
                ...$sunatOperacion,
                ...$inventarioOperacion,
                ...$catalogoLectura,
            ]),
        ];

        $createdRoles = [];

        foreach ($roles as $roleName => $permissionIds) {
            $role = Role::updateOrCreate([
                'empresa_id' => $empresa->id,
                'slug' => Str::slug($roleName),
            ], [
                'name' => $roleName,
                'description' => "Rol de {$roleName}",
                'active' => true,
            ]);

            $role->permissions()->sync($permissionIds);
            $createdRoles[$roleName] = $role;
        }

        return $createdRoles;
    }

    private function permissionIds(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('id')->all();
    }

    private function asignarTiendas(User $user, iterable $tiendas, int $tenantId, int $empresaId): void
    {
        foreach ($tiendas as $tienda) {
            $user->tiendas()->attach($tienda->id, [
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'estado' => true,
            ]);
        }
    }
}





