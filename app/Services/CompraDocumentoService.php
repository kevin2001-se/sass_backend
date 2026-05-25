<?php

namespace App\Services;

use App\Models\Compra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompraDocumentoService
{
    public function generarPdf(int $compraId, array $scope): Compra
    {
        $compra = $this->findScoped($compraId, $scope);
        $data = $this->obtenerDataDocumento($compra);

        try {
            $contenido = Pdf::loadView('pdf.compras.compra-a4', $data)->setPaper('a4')->output();
            $path = $this->path($compra);
            Storage::disk('local')->put($path, $contenido);
            $compra->update([
                'pdf_path' => $path,
                'pdf_generado_at' => now(),
            ]);

            return $this->findScoped($compra->id, $scope);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['documento' => ['No se pudo generar el PDF de compra. '.$e->getMessage()]]);
        }
    }

    public function descargarPdf(int $compraId, array $scope)
    {
        $compra = $this->findScoped($compraId, $scope);

        if (! $compra->pdf_path || ! Storage::disk('local')->exists($compra->pdf_path)) {
            throw ValidationException::withMessages(['archivo' => ['El PDF de la compra no existe.']]);
        }

        return Storage::disk('local')->download($compra->pdf_path, 'compra-'.$compra->serie.'-'.$compra->numero.'.pdf');
    }

    public function obtenerDataDocumento(Compra $compra): array
    {
        $compra->loadMissing([
            'empresa',
            'tienda',
            'proveedor',
            'user',
            'detalles.producto',
            'detalles.presentacion.unidadMedida',
            'detalles.lote',
        ]);

        return [
            'compra' => $compra,
            'empresa' => $compra->empresa,
            'proveedor' => $compra->proveedor,
            'tienda' => $compra->tienda,
            'detalles' => $compra->detalles,
            'anulada' => $compra->estado === Compra::ANULADA,
        ];
    }

    protected function findScoped(int $compraId, array $scope): Compra
    {
        return Compra::with([
                'empresa',
                'tienda',
                'proveedor',
                'user',
                'detalles.producto',
                'detalles.presentacion.unidadMedida',
                'detalles.lote',
            ])
            ->where('tenant_id', $scope['tenant_id'])
            ->where('empresa_id', $scope['empresa_id'])
            ->where('tienda_id', $scope['tienda_id'])
            ->findOrFail($compraId);
    }

    protected function path(Compra $compra): string
    {
        return 'private/compras/pdf/'.$compra->empresa_id.'/'.$compra->created_at->format('Y-m-d').'/compra-'.$compra->serie.'-'.$compra->numero.'.pdf';
    }
}
