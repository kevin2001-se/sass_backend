<?php

namespace App\Services;

use App\Models\NotaCredito;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotaCreditoDocumentoService
{
    public function generarPdfA4(int $notaId, array $scope): NotaCredito
    {
        $nota = $this->findScoped($notaId, $scope);
        $this->validarEstadoParaDocumento($nota);
        $data = $this->obtenerDataDocumento($nota);
        $contenido = Pdf::loadView('pdf.notas-credito.nota-credito-a4', $data)->setPaper('a4')->output();

        return $this->guardarDocumento($nota, 'PDF_A4', $contenido);
    }

    public function generarTicket80(int $notaId, array $scope): NotaCredito
    {
        $nota = $this->findScoped($notaId, $scope);
        $this->validarEstadoParaDocumento($nota);
        $data = $this->obtenerDataDocumento($nota);
        $contenido = Pdf::loadView('pdf.notas-credito.nota-credito-ticket-80', $data)
            ->setPaper([0, 0, 226.77, 841.89])
            ->output();

        return $this->guardarDocumento($nota, 'TICKET_80', $contenido);
    }

    public function generarTodosFormatos(int $notaId, array $scope): NotaCredito
    {
        $this->generarPdfA4($notaId, $scope);

        return $this->generarTicket80($notaId, $scope);
    }

    public function descargarPdfA4(int $notaId, array $scope)
    {
        $nota = $this->findScoped($notaId, $scope);

        return $this->downloadPrivate($nota->pdf_a4_path, $nota->numero_completo.'.pdf');
    }

    public function descargarTicket80(int $notaId, array $scope)
    {
        $nota = $this->findScoped($notaId, $scope);

        return $this->downloadPrivate($nota->ticket_80_path, $nota->numero_completo.'-ticket-80.pdf');
    }

    public function descargarXml(int $notaId, array $scope)
    {
        $nota = $this->findScoped($notaId, $scope);

        return $this->downloadPrivate($nota->xml_path, $nota->numero_completo.'.xml');
    }

    public function descargarCdr(int $notaId, array $scope)
    {
        $nota = $this->findScoped($notaId, $scope);

        return $this->downloadPrivate($nota->cdr_path, 'R-'.$nota->numero_completo.'.zip');
    }

    public function obtenerDataDocumento(NotaCredito $nota): array
    {
        $nota->loadMissing([
            'empresa.sunatConfiguraciones',
            'tienda',
            'venta.cliente',
            'comprobante',
            'motivo',
            'detalles.producto',
            'detalles.ventaDetalle.presentacion.unidadMedida',
        ]);

        $config = $nota->empresa->sunatConfiguraciones->firstWhere('estado', true);
        $cliente = $nota->venta?->cliente;

        return [
            'nota' => $nota,
            'empresa' => [
                'razon_social' => $config?->razon_social ?: $nota->empresa->nombre,
                'nombre_comercial' => $config?->nombre_comercial ?: $nota->empresa->nombre,
                'ruc' => $config?->ruc ?: $nota->empresa->ruc,
                'direccion' => $config?->direccion_fiscal ?: $nota->empresa->direccion,
            ],
            'cliente' => [
                'tipo_documento' => $cliente?->tipo_documento ?: 'SIN_DOCUMENTO',
                'numero_documento' => $cliente?->numero_documento ?: '00000000',
                'nombre' => $cliente ? ($cliente->razon_social ?: $cliente->nombres) : 'CLIENTES VARIOS',
            ],
            'comprobante' => $nota->comprobante,
            'motivo' => $nota->motivo,
            'detalles' => $nota->detalles,
            'qr' => $this->qrDataUri($nota->qr_text ?: $nota->numero_completo),
            'anulada' => $nota->estado === NotaCredito::ANULADA,
        ];
    }

    public function guardarDocumento(NotaCredito $nota, string $formato, string $contenido): NotaCredito
    {
        try {
            $path = $this->path($nota, $formato);
            Storage::disk('local')->put($path, $contenido);

            $updates = match ($formato) {
                'PDF_A4' => ['pdf_a4_path' => $path, 'pdf_generado_at' => now()],
                'TICKET_80' => ['ticket_80_path' => $path, 'ticket_generado_at' => now()],
                default => throw ValidationException::withMessages(['formato' => ['Formato no permitido.']]),
            };

            $nota->update($updates);

            return $this->findScoped($nota->id, [
                'tenant_id' => $nota->tenant_id,
                'empresa_id' => $nota->empresa_id,
                'tienda_id' => $nota->tienda_id,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['documento' => ['No se pudo generar el documento. '.$e->getMessage()]]);
        }
    }

    protected function validarEstadoParaDocumento(NotaCredito $nota): void
    {
        if ($nota->estado === NotaCredito::ANULADA) {
            return;
        }

        if ($nota->estado !== NotaCredito::REGISTRADA && $nota->estado_sunat !== NotaCredito::SUNAT_ACEPTADO) {
            throw ValidationException::withMessages([
                'estado' => ['Solo se pueden generar documentos para notas registradas, aceptadas o anuladas.'],
            ]);
        }
    }
    protected function findScoped(int $notaId, array $scope): NotaCredito
    {
        return NotaCredito::with([
                'empresa.sunatConfiguraciones',
                'tienda',
                'venta.cliente',
                'comprobante',
                'motivo',
                'detalles.producto',
                'detalles.ventaDetalle.presentacion.unidadMedida',
            ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($notaId);
    }

    protected function path(NotaCredito $nota, string $formato): string
    {
        $folder = match ($formato) {
            'PDF_A4' => 'pdf-a4',
            'TICKET_80' => 'ticket-80',
            default => 'otros',
        };

        return 'private/sunat/notas-credito/'.$nota->empresa_id.'/'.$nota->created_at->format('Y-m-d').'/'.$folder.'/'.$nota->numero_completo.'.pdf';
    }

    protected function downloadPrivate(?string $path, string $name)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => ['El archivo solicitado no existe.']]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    protected function qrDataUri(string $text): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 180,
            margin: 5,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }
}
