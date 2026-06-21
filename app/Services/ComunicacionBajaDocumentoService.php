<?php

namespace App\Services;

use App\Models\ComunicacionBaja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ComunicacionBajaDocumentoService
{
    public function generarPdfA4(int $id, array $scope): ComunicacionBaja
    {
        $comunicacion = $this->findScoped($id, $scope);
        $this->validarPdf($comunicacion);

        try {
            $contenido = Pdf::loadView('pdf.comunicaciones-baja.comunicacion-baja-a4', $this->obtenerDataDocumento($comunicacion))
                ->setPaper('a4')
                ->output();

            $path = $this->pdfPath($comunicacion);
            Storage::disk('local')->put($path, $contenido);

            $comunicacion->update([
                'pdf_a4_path' => $path,
                'pdf_generado_at' => now(),
            ]);

            return $this->findScoped($comunicacion->id, $scope);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['pdf' => ['No se pudo generar el PDF de la comunicacion de baja. '.$e->getMessage()]]);
        }
    }

    public function descargarPdfA4(int $id, array $scope)
    {
        $comunicacion = $this->findScoped($id, $scope);
        return $this->download($comunicacion->pdf_a4_path, $comunicacion->identificador.'.pdf', 'El PDF de la comunicacion de baja no esta disponible.');
    }

    public function descargarXml(int $id, array $scope)
    {
        $comunicacion = $this->findScoped($id, $scope);
        return $this->download($comunicacion->xml_path, $comunicacion->identificador.'.xml', 'El XML de la comunicacion de baja no esta disponible.');
    }

    public function descargarCdr(int $id, array $scope)
    {
        $comunicacion = $this->findScoped($id, $scope);
        return $this->download($comunicacion->cdr_path, 'R-'.$comunicacion->identificador.'.zip', 'El CDR de la comunicacion de baja no esta disponible.');
    }

    public function obtenerDataDocumento(ComunicacionBaja $comunicacion): array
    {
        $comunicacion->loadMissing(['empresa.sunatConfiguraciones', 'tienda', 'creadoPor', 'detalles.comprobante']);
        $configuracion = $comunicacion->empresa?->sunatConfiguraciones?->firstWhere('estado', true);

        return [
            'comunicacion' => $comunicacion,
            'empresa' => $comunicacion->empresa,
            'configuracion' => $configuracion,
            'tienda' => $comunicacion->tienda,
            'detalles' => $comunicacion->detalles,
            'anulado' => $comunicacion->estado === ComunicacionBaja::ANULADA,
        ];
    }

    protected function validarPdf(ComunicacionBaja $comunicacion): void
    {
        $permitidos = [
            ComunicacionBaja::REGISTRADA,
            ComunicacionBaja::ENVIADO,
            ComunicacionBaja::ACEPTADO,
            ComunicacionBaja::RECHAZADO,
            ComunicacionBaja::ERROR,
            ComunicacionBaja::ANULADA,
        ];

        if (! in_array($comunicacion->estado, $permitidos, true) && ! in_array($comunicacion->estado_sunat, $permitidos, true)) {
            throw ValidationException::withMessages(['estado' => ['No se puede generar PDF para el estado actual de la comunicacion de baja.']]);
        }
    }

    protected function findScoped(int $id, array $scope): ComunicacionBaja
    {
        return ComunicacionBaja::with(['empresa.sunatConfiguraciones', 'tienda', 'creadoPor', 'detalles.comprobante'])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($id);
    }

    protected function download(?string $path, string $name, string $message)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => [$message]]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    protected function pdfPath(ComunicacionBaja $comunicacion): string
    {
        return 'private/sunat/comunicaciones-baja/'.$comunicacion->empresa_id.'/'.$comunicacion->fecha_baja->format('Y-m-d').'/pdf-a4/'.$comunicacion->identificador.'.pdf';
    }
}
