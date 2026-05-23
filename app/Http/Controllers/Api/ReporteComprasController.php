<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Http\Resources\CompraResource;
use App\Services\Reportes\ReporteComprasService;

class ReporteComprasController extends Controller
{
    public function resumen(ReporteFiltroRequest $request, ReporteComprasService $service) { return response()->json(['data' => $service->resumenCompras($request->filtros())]); }
    public function productosMasComprados(ReporteFiltroRequest $request, ReporteComprasService $service) { return response()->json(['data' => $service->productosMasComprados($request->filtros())]); }
    public function detalle(ReporteFiltroRequest $request, ReporteComprasService $service) { return CompraResource::collection($service->comprasDetalle($request->filtros())); }
}
