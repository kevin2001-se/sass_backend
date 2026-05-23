<?php

namespace App\Services;

use App\Models\GuiaRemision;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class GuiaRemisionDocumentoService
{
    public function generarPdfA4(int $guiaId, array $scope): GuiaRemision
    {
        $guia = $this->findScoped($guiaId, $scope);
        $data = $this->obtenerDataDocumento($guia);
        $contenido = Pdf::loadView('pdf.guias-remision.guia-a4', $data)->setPaper('a4')->output();

        return $this->guardarDocumento($guia, 'PDF_A4', $contenido);
    }

    public function generarTicket80(int $guiaId, array $scope): GuiaRemision
    {
        $guia = $this->findScoped($guiaId, $scope);
        $data = $this->obtenerDataDocumento($guia);
        $contenido = Pdf::loadView('pdf.guias-remision.guia-ticket-80', $data)
            ->setPaper([0, 0, 226.77, 841.89])
            ->output();

        return $this->guardarDocumento($guia, 'TICKET_80', $contenido);
    }

    public function generarTodosFormatos(int $guiaId, array $scope): GuiaRemision
    {
        $this->generarPdfA4($guiaId, $scope);

        return $this->generarTicket80($guiaId, $scope);
    }

    public function descargarPdfA4(int $guiaId, array $scope)
    {
        $guia = $this->findScoped($guiaId, $scope);

        return $this->downloadPrivate($guia->pdf_a4_path, ($guia->numero_completo ?: $guia->numero_guia).'.pdf');
    }

    public function descargarTicket80(int $guiaId, array $scope)
    {
        $guia = $this->findScoped($guiaId, $scope);

        return $this->downloadPrivate($guia->ticket_80_path, ($guia->numero_completo ?: $guia->numero_guia).'-ticket-80.pdf');
    }

    public function obtenerDataDocumento(GuiaRemision $guia): array
    {
        $guia->loadMissing([
            'empresa.sunatConfiguraciones',
            'tienda',
            'cliente',
            'detalles.producto',
            'motivoTraslado',
            'modalidadTransporte',
            'venta',
            'comprobante',
        ]);

        $config = $guia->empresa->sunatConfiguraciones->firstWhere('estado', true);

        return [
            'guia' => $guia,
            'empresa' => [
                'razon_social' => $config?->razon_social ?: $guia->empresa->nombre,
                'nombre_comercial' => $config?->nombre_comercial ?: $guia->empresa->nombre,
                'ruc' => $config?->ruc ?: $guia->empresa->ruc,
                'direccion' => $config?->direccion_fiscal ?: $guia->empresa->direccion,
            ],
            'destinatario' => [
                'tipo_documento' => $guia->destinatario_tipo_documento,
                'numero_documento' => $guia->destinatario_numero_documento,
                'nombre' => $guia->destinatario_nombre,
            ],
            'detalles' => $guia->detalles,
            'qr' => $this->qrDataUri($guia->qr_text ?: ($guia->numero_completo ?: $guia->numero_guia)),
            'anulada' => $guia->estado === GuiaRemision::ANULADA,
        ];
    }

    public function guardarDocumento(GuiaRemision $guia, string $formato, string $contenido): GuiaRemision
    {
        try {
            $path = $this->path($guia, $formato);
            Storage::disk('local')->put($path, $contenido);

            $updates = match ($formato) {
                'PDF_A4' => ['pdf_a4_path' => $path, 'pdf_generado_at' => now()],
                'TICKET_80' => ['ticket_80_path' => $path, 'ticket_generado_at' => now()],
                default => throw ValidationException::withMessages(['formato' => ['Formato no permitido.']]),
            };

            $guia->update($updates);

            return $this->findScoped($guia->id, [
                'tenant_id' => $guia->tenant_id,
                'empresa_id' => $guia->empresa_id,
                'tienda_id' => $guia->tienda_id,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['documento' => ['No se pudo generar el documento. '.$e->getMessage()]]);
        }
    }

    protected function findScoped(int $guiaId, array $scope): GuiaRemision
    {
        return GuiaRemision::with([
                'empresa.sunatConfiguraciones',
                'tienda',
                'cliente',
                'detalles.producto',
                'motivoTraslado',
                'modalidadTransporte',
                'venta',
                'comprobante',
            ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($guiaId);
    }

    protected function path(GuiaRemision $guia, string $formato): string
    {
        $folder = match ($formato) {
            'PDF_A4' => 'pdf-a4',
            'TICKET_80' => 'ticket-80',
            default => 'otros',
        };

        return 'private/sunat/guias-remision/'.$guia->empresa_id.'/'.$guia->fecha_emision->format('Y-m-d').'/'.$folder.'/'.($guia->numero_completo ?: $guia->numero_guia).'.pdf';
    }

    protected function downloadPrivate(?string $path, string $name)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['archivo' => ['El archivo solicitado no existe. Genere el documento primero.']]);
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