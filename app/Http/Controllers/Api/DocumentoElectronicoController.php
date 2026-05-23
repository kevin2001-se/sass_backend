<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentoFiltroRequest;
use App\Http\Requests\GenerarDocumentoPdfRequest;
use App\Http\Resources\DocumentoElectronicoResource;
use App\Services\Sunat\DocumentoConsultaService;
use App\Services\Sunat\DocumentoPdfService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentoElectronicoController extends Controller
{
    public function __construct(
        private readonly DocumentoConsultaService $consultaService,
        private readonly DocumentoPdfService $pdfService
    ) {
    }

    public function index(DocumentoFiltroRequest $request)
    {
        return DocumentoElectronicoResource::collection($this->consultaService->buscarPorFecha($request->validated(), $request));
    }

    public function show(Request $request, int $id)
    {
        return new DocumentoElectronicoResource($this->consultaService->obtenerDetalle($id, $request));
    }

    public function generarPdfA4(Request $request, int $id)
    {
        return $this->generated($this->pdfService->generarPdfA4($id, $request), 'PDF A4 generado correctamente.');
    }

    public function generarTicket80(Request $request, int $id)
    {
        return $this->generated($this->pdfService->generarTicket80($id, $request), 'Ticket 80mm generado correctamente.');
    }

    public function generarTicket58(Request $request, int $id)
    {
        return $this->generated($this->pdfService->generarTicket58($id, $request), 'Ticket 58mm generado correctamente.');
    }

    public function generarFormatos(GenerarDocumentoPdfRequest $request, int $id)
    {
        $comprobante = match ($request->input('formato')) {
            'A4' => $this->pdfService->generarPdfA4($id, $request),
            'TICKET_80' => $this->pdfService->generarTicket80($id, $request),
            'TICKET_58' => $this->pdfService->generarTicket58($id, $request),
            'TODOS' => $this->pdfService->generarTodosFormatos($id, $request),
            default => throw ValidationException::withMessages(['formato' => ['Formato no permitido.']]),
        };

        return $this->generated($comprobante, 'Formatos generados correctamente.');
    }

    public function pdfA4(Request $request, int $id) { return $this->consultaService->obtenerPdf($id, 'A4', $request); }
    public function ticket80(Request $request, int $id) { return $this->consultaService->obtenerPdf($id, 'TICKET_80', $request); }
    public function ticket58(Request $request, int $id) { return $this->consultaService->obtenerPdf($id, 'TICKET_58', $request); }
    public function xml(Request $request, int $id) { return $this->consultaService->obtenerXml($id, $request); }
    public function cdr(Request $request, int $id) { return $this->consultaService->obtenerCdr($id, $request); }

    protected function generated($comprobante, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $comprobante->id,
                'tipo_comprobante' => $comprobante->tipo_comprobante,
                'numero_comprobante' => $comprobante->numero_comprobante,
                'pdf_a4_generado' => filled($comprobante->pdf_a4_path),
                'ticket_80_generado' => filled($comprobante->ticket_80_path),
                'ticket_58_generado' => filled($comprobante->ticket_58_path),
            ],
        ]);
    }
}
