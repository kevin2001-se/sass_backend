<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\AfectacionIgvController;
use App\Http\Controllers\Api\AccionTerapeuticaController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\CajaMovimientoController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ComprobanteElectronicoController;
use App\Http\Controllers\Api\ComunicacionBajaController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\CuentaPorPagarController;
use App\Http\Controllers\Api\CuentaPorCobrarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentoElectronicoController;
use App\Http\Controllers\Api\GuiaRemisionController;
use App\Http\Controllers\Api\GuiaRemisionDocumentoController;
use App\Http\Controllers\Api\GuiaRemisionSunatController;
use App\Http\Controllers\Api\GuiaRemisionVentaController;
use App\Http\Controllers\Api\InventarioMovimientoController;
use App\Http\Controllers\Api\LaboratorioController;
use App\Http\Controllers\Api\LoteController;
use App\Http\Controllers\Api\MarcaController;
use App\Http\Controllers\Api\ModalidadTransporteController;
use App\Http\Controllers\Api\MotivoTrasladoController;
use App\Http\Controllers\Api\NotaElectronicaController;
use App\Http\Controllers\Api\PrincipioActivoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProductoConfiguracionController;
use App\Http\Controllers\Api\PosProductoController;
use App\Http\Controllers\Api\PosClienteController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\ReporteCajaController;
use App\Http\Controllers\Api\ReporteComprasController;
use App\Http\Controllers\Api\ReporteFinancieroController;
use App\Http\Controllers\Api\ReporteInventarioController;
use App\Http\Controllers\Api\ReporteVentasController;
use App\Http\Controllers\Api\ResumenDiarioController;
use App\Http\Controllers\Api\SerieComprobanteController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SunatConfiguracionController;
use App\Http\Controllers\Api\UnidadMedidaController;
use App\Http\Controllers\Api\UnidadMedidaSunatController;
use App\Http\Controllers\Api\UbigeoController;
use App\Http\Controllers\Api\UserTiendaController;
use App\Http\Controllers\Api\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'resolve.tenant'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::get('tiendas/mis-tiendas', [UserTiendaController::class, 'misTiendas']);
    Route::post('tiendas/seleccionar', [UserTiendaController::class, 'seleccionar']);
    Route::get('dashboard/resumen', [DashboardController::class, 'resumen'])->middleware(['resolve.tienda', 'permission:dashboard.ver']);

    Route::get('dashboard', function () {
        return response()->json(['message' => 'Dashboard cargado con ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©xito.']);
    })->middleware(['resolve.tienda', 'permission:dashboard.ver']);

    Route::get('categorias', [CategoriaController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('categorias', [CategoriaController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('categorias/{categoria}', [CategoriaController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('categorias/{categoria}', [CategoriaController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('categorias/{categoria}', [CategoriaController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('marcas', [MarcaController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('marcas', [MarcaController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('marcas/{marca}', [MarcaController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('marcas/{marca}', [MarcaController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('marcas/{marca}', [MarcaController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('marcas/{marca}', [MarcaController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('laboratorios', [LaboratorioController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('laboratorios', [LaboratorioController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('laboratorios/{laboratorio}', [LaboratorioController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('laboratorios/{laboratorio}', [LaboratorioController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('laboratorios/{laboratorio}', [LaboratorioController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('laboratorios/{laboratorio}', [LaboratorioController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('principios-activos', [PrincipioActivoController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('principios-activos', [PrincipioActivoController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('principios-activos/{principio_activo}', [PrincipioActivoController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('principios-activos/{principio_activo}', [PrincipioActivoController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('principios-activos/{principio_activo}', [PrincipioActivoController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('principios-activos/{principio_activo}', [PrincipioActivoController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('acciones-terapeuticas', [AccionTerapeuticaController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('acciones-terapeuticas', [AccionTerapeuticaController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('acciones-terapeuticas/{accion_terapeutica}', [AccionTerapeuticaController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('acciones-terapeuticas/{accion_terapeutica}', [AccionTerapeuticaController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('acciones-terapeuticas/{accion_terapeutica}', [AccionTerapeuticaController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('acciones-terapeuticas/{accion_terapeutica}', [AccionTerapeuticaController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('afectaciones-igv', [AfectacionIgvController::class, 'index'])->middleware('permission:productos.ver');

    Route::get('unidades-medida', [UnidadMedidaController::class, 'index'])->middleware('permission:productos.ver');
    Route::post('unidades-medida', [UnidadMedidaController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('productos', [ProductoController::class, 'index'])->middleware('permission:productos.ver');
    Route::get('productos/configuracion', [ProductoConfiguracionController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('productos/configuracion', [ProductoConfiguracionController::class, 'update'])->middleware('permission:productos.editar');
    Route::post('productos', [ProductoController::class, 'store'])->middleware('permission:productos.crear');
    Route::get('productos/{producto}', [ProductoController::class, 'show'])->middleware('permission:productos.ver');
    Route::put('productos/{producto}', [ProductoController::class, 'update'])->middleware('permission:productos.editar');
    Route::patch('productos/{producto}', [ProductoController::class, 'update'])->middleware('permission:productos.editar');
    Route::delete('productos/{producto}', [ProductoController::class, 'destroy'])->middleware('permission:productos.eliminar');

    Route::get('lotes', [LoteController::class, 'index'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::post('lotes', [LoteController::class, 'store'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::get('lotes/{lote}', [LoteController::class, 'show'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::put('lotes/{lote}', [LoteController::class, 'update'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::patch('lotes/{lote}', [LoteController::class, 'update'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::delete('lotes/{lote}', [LoteController::class, 'destroy'])->middleware(['resolve.tienda', 'permission:inventario.ver']);

    Route::get('stocks', [StockController::class, 'index'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::get('stocks/producto/{productoId}', [StockController::class, 'producto'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::get('stocks/alertas', [StockController::class, 'alertas'])->middleware(['resolve.tienda', 'permission:inventario.ver']);

    Route::get('inventario/movimientos', [InventarioMovimientoController::class, 'index'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::post('inventario/entrada', [InventarioMovimientoController::class, 'entrada'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::post('inventario/salida', [InventarioMovimientoController::class, 'salida'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::post('inventario/ajuste', [InventarioMovimientoController::class, 'ajuste'])->middleware(['resolve.tienda', 'permission:inventario.ver']);
    Route::get('inventario/kardex/{productoId}', [InventarioMovimientoController::class, 'kardex'])->middleware(['resolve.tienda', 'permission:inventario.ver']);

    Route::get('clientes', [ClienteController::class, 'index'])->middleware('permission:ventas.ver');
    Route::post('clientes', [ClienteController::class, 'store'])->middleware('permission:ventas.crear');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->middleware('permission:ventas.ver');
    Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permission:ventas.crear');
    Route::patch('clientes/{cliente}', [ClienteController::class, 'update'])->middleware('permission:ventas.crear');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware('permission:ventas.crear');

    Route::get('series-comprobantes', [SerieComprobanteController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::post('series-comprobantes', [SerieComprobanteController::class, 'store'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::put('series-comprobantes/{serie_comprobante}', [SerieComprobanteController::class, 'update'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::patch('series-comprobantes/{serie_comprobante}', [SerieComprobanteController::class, 'update'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::delete('series-comprobantes/{serie_comprobante}', [SerieComprobanteController::class, 'destroy'])->middleware(['resolve.tienda', 'permission:ventas.crear']);

    Route::get('ventas', [VentaController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::post('ventas', [VentaController::class, 'store'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::get('ventas/{venta}/guia-remision-data', [GuiaRemisionVentaController::class, 'data'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::post('ventas/{venta}/ticket', [VentaController::class, 'generarTicket'])->middleware(['resolve.tienda', 'permission:ventas.imprimir']);
    Route::get('ventas/{venta}/ticket', [VentaController::class, 'ticket'])->middleware(['resolve.tienda', 'permission:ventas.imprimir']);
    Route::post('ventas/{venta}/pdf', [VentaController::class, 'generarPdf'])->middleware(['resolve.tienda', 'permission:ventas.exportar']);
    Route::get('ventas/{venta}/pdf', [VentaController::class, 'pdf'])->middleware(['resolve.tienda', 'permission:ventas.exportar']);


    Route::get('motivos-traslado', [MotivoTrasladoController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('modalidades-transporte', [ModalidadTransporteController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('unidades-medida-sunat', [UnidadMedidaSunatController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('ubigeo/departamentos', [UbigeoController::class, 'departamentos'])->middleware('permission:ventas.ver');
    Route::get('ubigeo/provincias', [UbigeoController::class, 'provincias'])->middleware('permission:ventas.ver');
    Route::get('ubigeo/distritos', [UbigeoController::class, 'distritos'])->middleware('permission:ventas.ver');
    Route::get('ubigeo/distritos/buscar', [UbigeoController::class, 'buscarDistritos'])->middleware('permission:ventas.ver');
    Route::get('guias-remision', [GuiaRemisionController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::post('guias-remision', [GuiaRemisionController::class, 'store'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::post('guias-remision/desde-venta/{venta}', [GuiaRemisionVentaController::class, 'crearDesdeVenta'])->middleware(['resolve.tienda', 'permission:guias.crear']);
    Route::get('guias-remision/{id}', [GuiaRemisionController::class, 'show'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::put('guias-remision/{id}', [GuiaRemisionController::class, 'update'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::patch('guias-remision/{id}', [GuiaRemisionController::class, 'update'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::post('guias-remision/{id}/anular', [GuiaRemisionController::class, 'anular'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::post('guias-remision/{id}/registrar', [GuiaRemisionController::class, 'registrar'])->middleware(['resolve.tienda', 'permission:guias.crear']);
    Route::post('guias-remision/{id}/generar-pdf-a4', [GuiaRemisionDocumentoController::class, 'generarPdfA4'])->middleware(['resolve.tienda', 'permission:guias.pdf.generar']);
    Route::post('guias-remision/{id}/generar-ticket-80', [GuiaRemisionDocumentoController::class, 'generarTicket80'])->middleware(['resolve.tienda', 'permission:guias.ticket.generar']);
    Route::post('guias-remision/{id}/generar-formatos', [GuiaRemisionDocumentoController::class, 'generarFormatos'])->middleware(['resolve.tienda', 'permission:guias.pdf.generar']);
    Route::get('guias-remision/{id}/pdf-a4', [GuiaRemisionDocumentoController::class, 'pdfA4'])->middleware(['resolve.tienda', 'permission:guias.pdf.descargar']);
    Route::get('guias-remision/{id}/ticket-80', [GuiaRemisionDocumentoController::class, 'ticket80'])->middleware(['resolve.tienda', 'permission:guias.ticket.descargar']);
    Route::post('guias-remision/{id}/enviar-sunat', [GuiaRemisionSunatController::class, 'enviar'])->middleware(['resolve.tienda', 'permission:guias.enviar_sunat']);
    Route::post('guias-remision/{id}/reenviar-sunat', [GuiaRemisionSunatController::class, 'reenviar'])->middleware(['resolve.tienda', 'permission:guias.reenviar_sunat']);
    Route::get('guias-remision/{id}/xml', [GuiaRemisionSunatController::class, 'xml'])->middleware(['resolve.tienda', 'permission:guias.descargar_xml']);
    Route::get('guias-remision/{id}/cdr', [GuiaRemisionSunatController::class, 'cdr'])->middleware(['resolve.tienda', 'permission:guias.descargar_cdr']);
    Route::get('pos/productos/buscar', [PosProductoController::class, 'buscar'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::get('pos/productos/rapidos', [PosProductoController::class, 'rapidos'])->middleware(['resolve.tienda', 'permission:ventas.crear']);
    Route::get('pos/clientes/buscar', [PosClienteController::class, 'buscar'])->middleware('permission:ventas.crear');

    Route::get('cajas', [CajaController::class, 'index'])->middleware(['resolve.tienda', 'permission:caja.ver']);
    Route::post('cajas/aperturar', [CajaController::class, 'aperturar'])->middleware(['resolve.tienda', 'permission:caja.aperturar']);
    Route::get('cajas/abierta', [CajaController::class, 'abierta'])->middleware(['resolve.tienda', 'permission:caja.ver']);
    Route::get('cajas/{caja}', [CajaController::class, 'show'])->middleware(['resolve.tienda', 'permission:caja.ver']);
    Route::post('cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->middleware(['resolve.tienda', 'permission:caja.cerrar']);
    Route::get('cajas/{caja}/arqueo', [CajaController::class, 'arqueo'])->middleware(['resolve.tienda', 'permission:caja.ver']);

    Route::get('caja-movimientos', [CajaMovimientoController::class, 'index'])->middleware(['resolve.tienda', 'permission:caja.ver']);
    Route::post('caja-movimientos/ingreso', [CajaMovimientoController::class, 'ingreso'])->middleware(['resolve.tienda', 'permission:caja.ingreso']);
    Route::post('caja-movimientos/egreso', [CajaMovimientoController::class, 'egreso'])->middleware(['resolve.tienda', 'permission:caja.egreso']);

    Route::get('proveedores', [ProveedorController::class, 'index'])->middleware('permission:compras.ver');
    Route::post('proveedores', [ProveedorController::class, 'store'])->middleware('permission:compras.crear');
    Route::get('proveedores/{proveedor}', [ProveedorController::class, 'show'])->middleware('permission:compras.ver');
    Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permission:compras.crear');
    Route::patch('proveedores/{proveedor}', [ProveedorController::class, 'update'])->middleware('permission:compras.crear');
    Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->middleware('permission:compras.crear');

    Route::get('compras', [CompraController::class, 'index'])->middleware(['resolve.tienda', 'permission:compras.ver']);
    Route::post('compras', [CompraController::class, 'store'])->middleware(['resolve.tienda', 'permission:compras.crear']);
    Route::get('compras/{compra}', [CompraController::class, 'show'])->middleware(['resolve.tienda', 'permission:compras.ver']);
    Route::post('compras/{compra}/anular', [CompraController::class, 'anular'])->middleware(['resolve.tienda', 'permission:compras.crear']);

    Route::get('cuentas-por-pagar', [CuentaPorPagarController::class, 'index'])->middleware(['resolve.tienda', 'permission:compras.ver']);
    Route::get('cuentas-por-pagar/{cuenta}', [CuentaPorPagarController::class, 'show'])->middleware(['resolve.tienda', 'permission:compras.ver']);
    Route::post('cuentas-por-pagar/{cuenta}/pagar', [CuentaPorPagarController::class, 'pagar'])->middleware(['resolve.tienda', 'permission:compras.crear']);

    Route::get('cuentas-por-cobrar', [CuentaPorCobrarController::class, 'index'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('cuentas-por-cobrar/vencidas', [CuentaPorCobrarController::class, 'vencidas'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('cuentas-por-cobrar/cliente/{clienteId}', [CuentaPorCobrarController::class, 'cliente'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::get('cuentas-por-cobrar/{cuenta}', [CuentaPorCobrarController::class, 'show'])->middleware(['resolve.tienda', 'permission:ventas.ver']);
    Route::post('cuentas-por-cobrar/{cuenta}/pagar', [CuentaPorCobrarController::class, 'pagar'])->middleware(['resolve.tienda', 'permission:ventas.crear']);

    Route::get('reportes/ventas/resumen', [ReporteVentasController::class, 'resumen'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/ventas/metodos-pago', [ReporteVentasController::class, 'metodosPago'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/ventas/productos-mas-vendidos', [ReporteVentasController::class, 'productosMasVendidos'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/ventas/detalle', [ReporteVentasController::class, 'detalle'])->middleware(['resolve.tienda', 'permission:reportes.ver']);

    Route::get('reportes/compras/resumen', [ReporteComprasController::class, 'resumen'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/compras/productos-mas-comprados', [ReporteComprasController::class, 'productosMasComprados'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/compras/detalle', [ReporteComprasController::class, 'detalle'])->middleware(['resolve.tienda', 'permission:reportes.ver']);

    Route::get('reportes/inventario/stock-actual', [ReporteInventarioController::class, 'stockActual'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/inventario/stock-valorizado', [ReporteInventarioController::class, 'stockValorizado'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/inventario/bajo-stock', [ReporteInventarioController::class, 'bajoStock'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/inventario/lotes-por-vencer', [ReporteInventarioController::class, 'lotesPorVencer'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/inventario/lotes-vencidos', [ReporteInventarioController::class, 'lotesVencidos'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/inventario/kardex', [ReporteInventarioController::class, 'kardex'])->middleware(['resolve.tienda', 'permission:reportes.ver']);

    Route::get('reportes/caja/resumen', [ReporteCajaController::class, 'resumen'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/caja/metodos-pago', [ReporteCajaController::class, 'metodosPago'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/caja/cierres', [ReporteCajaController::class, 'cierres'])->middleware(['resolve.tienda', 'permission:reportes.ver']);

    Route::get('reportes/financiero/cuentas-por-cobrar', [ReporteFinancieroController::class, 'cuentasPorCobrar'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/financiero/cuentas-por-pagar', [ReporteFinancieroController::class, 'cuentasPorPagar'])->middleware(['resolve.tienda', 'permission:reportes.ver']);
    Route::get('reportes/financiero/flujo', [ReporteFinancieroController::class, 'flujo'])->middleware(['resolve.tienda', 'permission:reportes.ver']);

    Route::get('sunat/configuracion', [SunatConfiguracionController::class, 'show'])->middleware('permission:sunat.configuracion.ver');
    Route::post('sunat/configuracion', [SunatConfiguracionController::class, 'store'])->middleware('permission:sunat.configuracion.crear');
    Route::post('sunat/configuracion/probar-gre', [SunatConfiguracionController::class, 'probarGre'])->middleware('permission:sunat.configuracion.ver');
    Route::put('sunat/configuracion/{configuracion}', [SunatConfiguracionController::class, 'update'])->middleware('permission:sunat.configuracion.editar');
    Route::post('sunat/configuracion/{configuracion}', [SunatConfiguracionController::class, 'update'])->middleware('permission:sunat.configuracion.editar');
    Route::delete('sunat/configuracion/{configuracion}', [SunatConfiguracionController::class, 'destroy'])->middleware('permission:sunat.configuracion.eliminar');

    Route::get('sunat/comprobantes', [ComprobanteElectronicoController::class, 'index'])->middleware('resolve.tienda');
    Route::post('sunat/comprobantes/emitir/{ventaId}', [ComprobanteElectronicoController::class, 'emitir'])->middleware('resolve.tienda');
    Route::post('sunat/comprobantes/{comprobante}/reenviar', [ComprobanteElectronicoController::class, 'reenviar'])->middleware('resolve.tienda');
    Route::get('sunat/comprobantes/{comprobante}', [ComprobanteElectronicoController::class, 'show'])->middleware('resolve.tienda');
    Route::get('sunat/comprobantes/{comprobante}/xml', [ComprobanteElectronicoController::class, 'xml'])->middleware('resolve.tienda');
    Route::get('sunat/comprobantes/{comprobante}/cdr', [ComprobanteElectronicoController::class, 'cdr'])->middleware('resolve.tienda');

    Route::get('sunat/notas', [NotaElectronicaController::class, 'index'])->middleware('resolve.tienda');
    Route::post('sunat/notas/credito', [NotaElectronicaController::class, 'credito'])->middleware('resolve.tienda');
    Route::post('sunat/notas/debito', [NotaElectronicaController::class, 'debito'])->middleware('resolve.tienda');
    Route::get('sunat/notas/{nota}', [NotaElectronicaController::class, 'show'])->middleware('resolve.tienda');
    Route::post('sunat/notas/{nota}/anular', [NotaElectronicaController::class, 'anular'])->middleware('resolve.tienda');
    Route::post('sunat/notas/{nota}/reenviar', [NotaElectronicaController::class, 'reenviar'])->middleware('resolve.tienda');

    Route::get('sunat/resumenes-diarios', [ResumenDiarioController::class, 'index'])->middleware('resolve.tienda');
    Route::post('sunat/resumenes-diarios/generar', [ResumenDiarioController::class, 'generar'])->middleware('resolve.tienda');
    Route::post('sunat/resumenes-diarios/{id}/enviar', [ResumenDiarioController::class, 'enviar'])->middleware('resolve.tienda');
    Route::post('sunat/resumenes-diarios/{id}/consultar-ticket', [ResumenDiarioController::class, 'consultarTicket'])->middleware('resolve.tienda');
    Route::post('sunat/resumenes-diarios/{id}/reenviar', [ResumenDiarioController::class, 'reenviar'])->middleware('resolve.tienda');
    Route::get('sunat/resumenes-diarios/{id}', [ResumenDiarioController::class, 'show'])->middleware('resolve.tienda');
    Route::get('sunat/resumenes-diarios/{id}/xml', [ResumenDiarioController::class, 'xml'])->middleware('resolve.tienda');
    Route::get('sunat/resumenes-diarios/{id}/cdr', [ResumenDiarioController::class, 'cdr'])->middleware('resolve.tienda');

    Route::get('sunat/comunicaciones-baja', [ComunicacionBajaController::class, 'index'])->middleware('resolve.tienda');
    Route::post('sunat/comunicaciones-baja/generar', [ComunicacionBajaController::class, 'generar'])->middleware('resolve.tienda');
    Route::post('sunat/comunicaciones-baja/{id}/enviar', [ComunicacionBajaController::class, 'enviar'])->middleware('resolve.tienda');
    Route::post('sunat/comunicaciones-baja/{id}/consultar-ticket', [ComunicacionBajaController::class, 'consultarTicket'])->middleware('resolve.tienda');
    Route::post('sunat/comunicaciones-baja/{id}/reenviar', [ComunicacionBajaController::class, 'reenviar'])->middleware('resolve.tienda');
    Route::get('sunat/comunicaciones-baja/{id}', [ComunicacionBajaController::class, 'show'])->middleware('resolve.tienda');
    Route::get('sunat/comunicaciones-baja/{id}/xml', [ComunicacionBajaController::class, 'xml'])->middleware('resolve.tienda');
    Route::get('sunat/comunicaciones-baja/{id}/cdr', [ComunicacionBajaController::class, 'cdr'])->middleware('resolve.tienda');

    Route::get('sunat/guias-remision', [GuiaRemisionController::class, 'index'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision', [GuiaRemisionController::class, 'store'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision/desde-venta/{ventaId}', [GuiaRemisionController::class, 'desdeVenta'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision/desde-compra/{compraId}', [GuiaRemisionController::class, 'desdeCompra'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision/{id}/enviar', [GuiaRemisionController::class, 'enviar'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision/{id}/reenviar', [GuiaRemisionController::class, 'reenviar'])->middleware('resolve.tienda');
    Route::post('sunat/guias-remision/{id}/anular', [GuiaRemisionController::class, 'anular'])->middleware('resolve.tienda');
    Route::get('sunat/guias-remision/{id}', [GuiaRemisionController::class, 'show'])->middleware('resolve.tienda');
    Route::get('sunat/guias-remision/{id}/xml', [GuiaRemisionController::class, 'xml'])->middleware('resolve.tienda');
    Route::get('sunat/guias-remision/{id}/cdr', [GuiaRemisionController::class, 'cdr'])->middleware('resolve.tienda');

    Route::get('sunat/documentos', [DocumentoElectronicoController::class, 'index'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}', [DocumentoElectronicoController::class, 'show'])->middleware('resolve.tienda');
    Route::post('sunat/documentos/{id}/generar-pdf-a4', [DocumentoElectronicoController::class, 'generarPdfA4'])->middleware('resolve.tienda');
    Route::post('sunat/documentos/{id}/generar-ticket-80', [DocumentoElectronicoController::class, 'generarTicket80'])->middleware('resolve.tienda');
    Route::post('sunat/documentos/{id}/generar-ticket-58', [DocumentoElectronicoController::class, 'generarTicket58'])->middleware('resolve.tienda');
    Route::post('sunat/documentos/{id}/generar-formatos', [DocumentoElectronicoController::class, 'generarFormatos'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}/pdf-a4', [DocumentoElectronicoController::class, 'pdfA4'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}/ticket-80', [DocumentoElectronicoController::class, 'ticket80'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}/ticket-58', [DocumentoElectronicoController::class, 'ticket58'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}/xml', [DocumentoElectronicoController::class, 'xml'])->middleware('resolve.tienda');
    Route::get('sunat/documentos/{id}/cdr', [DocumentoElectronicoController::class, 'cdr'])->middleware('resolve.tienda');
});










