<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Http\Resources\CuentaPorCobrarResource;
use App\Http\Resources\CuentaPorPagarResource;
use App\Services\Reportes\ReporteFinancieroService;

class ReporteFinancieroController extends Controller
{
    public function cuentasPorCobrar(ReporteFiltroRequest $request, ReporteFinancieroService $service) { return CuentaPorCobrarResource::collection($service->cuentasPorCobrar($request->filtros())); }
    public function cuentasPorPagar(ReporteFiltroRequest $request, ReporteFinancieroService $service) { return CuentaPorPagarResource::collection($service->cuentasPorPagar($request->filtros())); }
    public function flujo(ReporteFiltroRequest $request, ReporteFinancieroService $service) { return response()->json(['data' => $service->flujoIngresosEgresos($request->filtros())]); }
}
