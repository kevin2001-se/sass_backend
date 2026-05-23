<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Services\Reportes\DashboardService;

class DashboardController extends Controller
{
    public function resumen(ReporteFiltroRequest $request, DashboardService $service)
    {
        return response()->json(['data' => $service->obtenerResumen($request->filtros())]);
    }
}
