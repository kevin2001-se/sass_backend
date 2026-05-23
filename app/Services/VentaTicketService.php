<?php

namespace App\Services;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VentaTicketService
{
    public function generarTicket80(int $ventaId, Request $request): string
    {
        $venta = $this->findScoped($ventaId, $request);

        $contenido = Pdf::loadView('pdf.ventas.ticket-80', $this->data($venta))
            ->setPaper([0, 0, 226.77, 841.89])
            ->output();

        $path = $this->ticketPath($venta);
        Storage::disk('local')->put($path, $contenido);

        return $path;
    }

    public function descargarTicket80(int $ventaId, Request $request)
    {
        $venta = $this->findScoped($ventaId, $request);
        $path = $this->ticketPath($venta);

        if (! Storage::disk('local')->exists($path)) {
            $this->generarTicket80($ventaId, $request);
        }

        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['ticket' => ['Ticket no disponible.']]);
        }

        return Storage::disk('local')->download($path, $venta->numero_comprobante.'-ticket-80.pdf');
    }

    public function generarPdfA4(int $ventaId, Request $request): string
    {
        $venta = $this->findScoped($ventaId, $request);

        $contenido = Pdf::loadView('pdf.ventas.nota-venta-a4', $this->data($venta))
            ->setPaper('a4')
            ->output();

        $path = $this->pdfPath($venta);
        Storage::disk('local')->put($path, $contenido);

        return $path;
    }

    public function descargarPdfA4(int $ventaId, Request $request)
    {
        $venta = $this->findScoped($ventaId, $request);
        $path = $this->pdfPath($venta);

        if (! Storage::disk('local')->exists($path)) {
            $this->generarPdfA4($ventaId, $request);
        }

        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['pdf' => ['PDF no disponible.']]);
        }

        return Storage::disk('local')->download($path, $venta->numero_comprobante.'-a4.pdf');
    }

    protected function findScoped(int $ventaId, Request $request): Venta
    {
        return Venta::with(['empresa', 'tienda', 'cliente', 'user', 'detalles.producto', 'detalles.presentacion', 'detalles.lote', 'pagos'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($ventaId);
    }

    protected function data(Venta $venta): array
    {
        return [
            'venta' => $venta,
            'empresa' => $venta->empresa,
            'tienda' => $venta->tienda,
            'cliente' => $venta->cliente,
            'detalles' => $venta->detalles,
            'pagos' => $venta->pagos,
        ];
    }

    protected function ticketPath(Venta $venta): string
    {
        return 'private/ventas/tickets/'.$venta->empresa_id.'/'.$venta->fecha_emision->format('Y-m-d').'/'.$venta->numero_comprobante.'-ticket-80.pdf';
    }

    protected function pdfPath(Venta $venta): string
    {
        return 'private/ventas/pdf/'.$venta->empresa_id.'/'.$venta->fecha_emision->format('Y-m-d').'/'.$venta->numero_comprobante.'-a4.pdf';
    }
}