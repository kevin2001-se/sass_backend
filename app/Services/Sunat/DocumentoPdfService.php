<?php

namespace App\Services\Sunat;

use App\Models\ComprobanteElectronico;
use App\Models\GuiaRemision;
use App\Models\NotaElectronica;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentoPdfService
{
    private const TIPOS_PERMITIDOS = ['FACTURA', 'BOLETA', 'NOTA_CREDITO', 'NOTA_DEBITO', 'GUIA_REMISION'];

    public function __construct(private readonly DocumentoConsultaService $consultaService)
    {
    }

    public function generarPdfA4(int $comprobanteId, Request $request): ComprobanteElectronico
    {
        $comprobante = $this->consultaService->findScoped($comprobanteId, $request);
        $data = $this->obtenerDataDocumento($comprobante);
        $view = match ($comprobante->tipo_comprobante) {
            Venta::FACTURA => 'pdf.sunat.factura-a4',
            Venta::BOLETA => 'pdf.sunat.boleta-a4',
            NotaElectronica::NOTA_CREDITO, NotaElectronica::NOTA_DEBITO => 'pdf.sunat.nota-a4',
            'GUIA_REMISION' => 'pdf.sunat.guia-a4',
            default => throw ValidationException::withMessages(['tipo_comprobante' => ['Tipo de documento no permitido para PDF.']]),
        };

        $contenido = Pdf::loadView($view, $data)->setPaper('a4')->output();

        return $this->guardarPdf($comprobante, 'A4', $contenido);
    }

    public function generarTicket80(int $comprobanteId, Request $request): ComprobanteElectronico
    {
        $comprobante = $this->consultaService->findScoped($comprobanteId, $request);
        $contenido = Pdf::loadView('pdf.sunat.ticket-80', $this->obtenerDataDocumento($comprobante))
            ->setPaper([0, 0, 226.77, 841.89])
            ->output();

        return $this->guardarPdf($comprobante, 'TICKET_80', $contenido);
    }

    public function generarTicket58(int $comprobanteId, Request $request): ComprobanteElectronico
    {
        $comprobante = $this->consultaService->findScoped($comprobanteId, $request);
        $contenido = Pdf::loadView('pdf.sunat.ticket-58', $this->obtenerDataDocumento($comprobante))
            ->setPaper([0, 0, 164.41, 841.89])
            ->output();

        return $this->guardarPdf($comprobante, 'TICKET_58', $contenido);
    }

    public function generarTodosFormatos(int $comprobanteId, Request $request): ComprobanteElectronico
    {
        $this->generarPdfA4($comprobanteId, $request);
        $this->generarTicket80($comprobanteId, $request);

        return $this->generarTicket58($comprobanteId, $request);
    }

    public function obtenerDataDocumento(ComprobanteElectronico $comprobante): array
    {
        $comprobante->loadMissing([
            'empresa.sunatConfiguraciones',
            'tienda',
            'venta.cliente',
            'venta.detalles',
            'venta.pagos',
            'notaElectronica.venta.cliente',
            'notaElectronica.detalles',
            'notaElectronica.comprobanteReferencia',
            'guiaRemision.cliente',
            'guiaRemision.proveedor',
            'guiaRemision.detalles',
            'guiaRemision.documentosRelacionados',
        ]);
        $this->validarTipo($comprobante);

        $empresaConfig = $comprobante->empresa->sunatConfiguraciones->firstWhere('estado', true);
        $totales = $this->totales($comprobante);

        return [
            'comprobante' => $comprobante,
            'empresa' => [
                'razon_social' => $empresaConfig?->razon_social ?: $comprobante->empresa->nombre,
                'nombre_comercial' => $empresaConfig?->nombre_comercial ?: $comprobante->empresa->nombre,
                'ruc' => $empresaConfig?->ruc ?: $comprobante->empresa->ruc,
                'direccion' => $empresaConfig?->direccion_fiscal ?: $comprobante->empresa->direccion,
            ],
            'cliente' => $this->cliente($comprobante),
            'guia' => $comprobante->guiaRemision,
            'detalles' => $this->detalles($comprobante),
            'pagos' => $comprobante->venta?->pagos ?? collect(),
            'totales' => $totales,
            'total_letras' => $this->totalLetras((float) ($totales['total'] ?? 0)),
            'qr' => $this->qrDataUri($comprobante->qr_text ?: $comprobante->numero_comprobante),
        ];
    }

    public function guardarPdf(ComprobanteElectronico $comprobante, string $formato, string $contenido): ComprobanteElectronico
    {
        $path = $this->path($comprobante, $formato);
        Storage::disk('local')->put($path, $contenido);

        $updates = match ($formato) {
            'A4' => ['pdf_a4_path' => $path, 'pdf_generado_at' => now()],
            'TICKET_80' => ['ticket_80_path' => $path, 'ticket_generado_at' => now()],
            'TICKET_58' => ['ticket_58_path' => $path, 'ticket_generado_at' => now()],
            default => throw ValidationException::withMessages(['formato' => ['Formato no permitido.']]),
        };

        $comprobante->update($updates);

        return $comprobante->refresh();
    }

    public function descargarPdf(int $comprobanteId, string $formato, Request $request)
    {
        return $this->consultaService->obtenerPdf($comprobanteId, $formato, $request);
    }

    protected function validarTipo(ComprobanteElectronico $comprobante): void
    {
        if (! in_array($comprobante->tipo_comprobante, self::TIPOS_PERMITIDOS, true)) {
            throw ValidationException::withMessages(['tipo_comprobante' => ['No se genera PDF para este tipo de documento.']]);
        }
    }

    protected function path(ComprobanteElectronico $comprobante, string $formato): string
    {
        $folder = match ($formato) {
            'A4' => 'a4',
            'TICKET_80' => 'ticket80',
            'TICKET_58' => 'ticket58',
            default => 'otros',
        };

        return 'private/sunat/pdf/'.$comprobante->empresa_id.'/'.$comprobante->tipo_comprobante.'/'.$comprobante->fecha_emision->format('Y-m-d').'/'.$folder.'/'.$comprobante->numero_comprobante.'.pdf';
    }

    protected function cliente(ComprobanteElectronico $comprobante): array
    {
        $cliente = $comprobante->venta?->cliente ?: $comprobante->notaElectronica?->venta?->cliente ?: $comprobante->guiaRemision?->cliente;
        $proveedor = $comprobante->guiaRemision?->proveedor;

        return [
            'nombre' => $cliente?->razon_social ?: $cliente?->nombres ?: $proveedor?->razon_social ?: 'CLIENTES VARIOS',
            'tipo_documento' => $cliente?->tipo_documento ?: $proveedor?->tipo_documento ?: 'SIN_DOCUMENTO',
            'numero_documento' => $cliente?->numero_documento ?: $proveedor?->numero_documento ?: '00000000',
            'direccion' => $cliente?->direccion ?: $proveedor?->direccion,
        ];
    }

    protected function detalles(ComprobanteElectronico $comprobante): array
    {
        if ($comprobante->venta) {
            return $comprobante->venta->detalles->map(fn ($d) => [
                'producto_id' => $d->producto_id,
                'descripcion' => $d->descripcion,
                'cantidad' => $d->cantidad_presentacion,
                'unidad_medida' => 'NIU',
                'precio_unitario' => $d->precio_unitario,
                'descuento' => $d->descuento,
                'subtotal' => $d->subtotal,
                'igv' => $d->igv,
                'total' => $d->total,
            ])->all();
        }

        if ($comprobante->notaElectronica) {
            return $comprobante->notaElectronica->detalles->map(fn ($d) => [
                'producto_id' => $d->producto_id,
                'descripcion' => $d->descripcion,
                'cantidad' => $d->cantidad_presentacion,
                'unidad_medida' => 'NIU',
                'precio_unitario' => $d->precio_unitario,
                'subtotal' => $d->subtotal,
                'igv' => $d->igv,
                'total' => $d->total,
            ])->all();
        }

        return $comprobante->guiaRemision?->detalles->map(fn ($d) => [
            'producto_id' => $d->producto_id,
            'descripcion' => $d->descripcion,
            'cantidad' => $d->cantidad,
            'unidad_medida' => $d->unidad_medida,
        ])->all() ?? [];
    }

    protected function totales(ComprobanteElectronico $comprobante): array
    {
        if ($comprobante->venta) {
            return [
                'subtotal' => (float) $comprobante->venta->subtotal,
                'igv' => (float) $comprobante->venta->total_igv,
                'descuento' => (float) $comprobante->venta->total_descuento,
                'total' => (float) $comprobante->venta->total,
            ];
        }

        if ($comprobante->notaElectronica) {
            return [
                'subtotal' => (float) $comprobante->notaElectronica->subtotal,
                'igv' => (float) $comprobante->notaElectronica->total_igv,
                'descuento' => 0,
                'total' => (float) $comprobante->notaElectronica->total,
            ];
        }

        return ['subtotal' => 0, 'igv' => 0, 'descuento' => 0, 'total' => 0];
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

    protected function totalLetras(float $total): string
    {
        return 'SON '.number_format($total, 2, '.', '').' SOLES';
    }
}
