<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteFiltroRequest;
use App\Services\Reportes\ReporteExportService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

class ReporteExportController extends Controller
{
    public function excel(ReporteFiltroRequest $request, ReporteExportService $service, string $grupo, string $reporte)
    {
        try {
            return $service->descargarExcel($grupo, $reporte, $request->filtros());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);
            return $this->error('No se pudo generar el Excel del reporte.', 500);
        }
    }

    public function pdf(ReporteFiltroRequest $request, ReporteExportService $service, string $grupo, string $reporte)
    {
        try {
            return $service->descargarPdf($grupo, $reporte, $request->filtros());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);
            return $this->error('No se pudo generar el PDF del reporte.', 500);
        }
    }

    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
