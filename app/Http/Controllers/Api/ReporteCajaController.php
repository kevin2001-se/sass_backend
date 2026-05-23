<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Http\Resources\CajaResource;
use App\Services\Reportes\ReporteCajaService;

class ReporteCajaController extends Controller
{
    public function resumen(ReporteFiltroRequest $request, ReporteCajaService $service) { return response()->json(['data' => $service->resumenCaja($request->filtros())]); }
    public function metodosPago(ReporteFiltroRequest $request, ReporteCajaService $service) { return response()->json(['data' => $service->ventasPorMetodoPago($request->filtros())]); }
    public function cierres(ReporteFiltroRequest $request, ReporteCajaService $service) { return CajaResource::collection($service->historialCierres($request->filtros())); }
}
