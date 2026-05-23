<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Http\Resources\VentaResource;
use App\Services\Reportes\ReporteVentasService;

class ReporteVentasController extends Controller
{
    public function resumen(ReporteFiltroRequest $request, ReporteVentasService $service) { return response()->json(['data' => $service->resumenVentas($request->filtros())]); }
    public function metodosPago(ReporteFiltroRequest $request, ReporteVentasService $service) { return response()->json(['data' => $service->ventasPorMetodoPago($request->filtros())]); }
    public function productosMasVendidos(ReporteFiltroRequest $request, ReporteVentasService $service) { return response()->json(['data' => $service->productosMasVendidos($request->filtros())]); }
    public function detalle(ReporteFiltroRequest $request, ReporteVentasService $service) { return VentaResource::collection($service->ventasDetalle($request->filtros())); }
}
