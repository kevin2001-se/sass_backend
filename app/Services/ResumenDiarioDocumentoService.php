<?php

namespace App\Services;

use App\Models\ResumenDiario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResumenDiarioDocumentoService
{
    public function generarPdfA4(int $resumenId, array $scope): ResumenDiario
    {
        $resumen = $this->findScoped($resumenId, $scope);
        $this->validarPdf($resumen);

        try {
            $contenido = Pdf::loadView('pdf.resumenes-diarios.resumen-diario-a4', $this->obtenerDataDocumento($resumen))
                ->setPaper('a4')
                ->output();

            $path = $this->pdfPath($resumen);
            Storage::disk('local')->put($path, $contenido);

            $resumen->update([
                'pdf_a4_path' => $path,
                'pdf_generado_at' => now(),
            ]);

            return $this->findScoped($resumen->id, $scope);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['pdf' => ['No se pudo generar el PDF del resumen diario. '.$e->getMessage()]]);
        }
    }

    public function descargarPdfA4(int $resumenId, array $scope)
    {
        $resumen = $this->findScoped($resumenId, $scope);
        return $this->download($resumen->pdf_a4_path, $resumen->identificador.'.pdf', 'El PDF del resumen diario no esta disponible.');
    }

    public function descargarXml(int $resumenId, array $scope)
    {
        $resumen = $this->findScoped($resumenId, $scope);
        return $this->download($resumen->xml_path, $resumen->identificador.'.xml', 'El XML del resumen diario no esta disponible.');
    }

    public function descargarCdr(int $resumenId, array $scope)
    {
        $resumen = $this->findScoped($resumenId, $scope);
        return $this->download($resumen->cdr_path, 'R-'.$resumen->identificador.'.zip', 'El CDR del resumen diario no esta disponible.');
    }

    public function obtenerDataDocumento(ResumenDiario $resumen): array
    {
        $resumen->loadMissing(['empresa.sunatConfiguraciones', 'tienda', 'creadoPor', 'detalles']);
        $configuracion = $resumen->empresa?->sunatConfiguraciones?->firstWhere('estado', true);

        return [
            'resumen' => $resumen,
            'empresa' => $resumen->empresa,
            'configuracion' => $configuracion,
            'tienda' => $resumen->tienda,
            'detalles' => $resumen->detalles,
            'anulado' => $resumen->estado === ResumenDiario::ANULADO,
        ];
    }

    protected function validarPdf(ResumenDiario $resumen): void
    {
        $permitidos = [
            ResumenDiario::REGISTRADO,
            ResumenDiario::ENVIADO,
            ResumenDiario::ACEPTADO,
            ResumenDiario::RECHAZADO,
            ResumenDiario::ERROR,
            ResumenDiario::ANULADO,
        ];

        if (! in_array($resumen->estado, $permitidos, true) && ! in_array($resumen->estado_sunat, $permitidos, true)) {
            throw ValidationException::withMessages(['estado' => ['No se puede generar PDF para el estado actual del resumen diario.']]);
        }
    }

    protected function findScoped(int $resumenId, array $scope): ResumenDiario
    {
        return ResumenDiario::with(['empresa.sunatConfiguraciones', 'tienda', 'creadoPor', 'detalles'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($resumenId);
    }

    protected function download(?string $path, string $name, string $message)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => [$message]]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    protected function pdfPath(ResumenDiario $resumen): string
    {
        return 'private/sunat/resumenes-diarios/'.$resumen->empresa_id.'/'.$resumen->fecha_resumen->format('Y-m-d').'/pdf-a4/'.$resumen->identificador.'.pdf';
    }
}