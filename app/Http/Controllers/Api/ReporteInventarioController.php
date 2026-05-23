<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Http\Resources\InventarioMovimientoResource;
use App\Http\Resources\LoteResource;
use App\Http\Resources\StockResource;
use App\Services\Reportes\ReporteInventarioService;

class ReporteInventarioController extends Controller
{
    public function stockActual(ReporteFiltroRequest $request, ReporteInventarioService $service) { return StockResource::collection($service->stockActual($request->filtros())); }
    public function stockValorizado(ReporteFiltroRequest $request, ReporteInventarioService $service) { return response()->json(['data' => $service->stockValorizado($request->filtros())]); }
    public function bajoStock(ReporteFiltroRequest $request, ReporteInventarioService $service) { return StockResource::collection($service->productosBajoStock($request->filtros())); }
    public function lotesPorVencer(ReporteFiltroRequest $request, ReporteInventarioService $service) { return LoteResource::collection($service->lotesPorVencer($request->filtros())); }
    public function lotesVencidos(ReporteFiltroRequest $request, ReporteInventarioService $service) { return LoteResource::collection($service->lotesVencidos($request->filtros())); }
    public function kardex(ReporteFiltroRequest $request, ReporteInventarioService $service) { return InventarioMovimientoResource::collection($service->kardexProducto($request->filtros())); }
}
