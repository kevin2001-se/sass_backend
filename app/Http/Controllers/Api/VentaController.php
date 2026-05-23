<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Services\VentaService;
use App\Services\VentaTicketService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(private readonly VentaService $ventaService, private readonly VentaTicketService $ventaTicketService)
    {
    }

    public function index(Request $request)
    {
        $ventas = Venta::with(['cliente', 'user', 'pagos', 'comprobanteElectronico'])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->filled('fecha_inicio'), fn ($query) => $query->whereDate('fecha_emision', '>=', $request->date('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn ($query) => $query->whereDate('fecha_emision', '<=', $request->date('fecha_fin')))
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->input('estado')))
            ->when($request->filled('tipo_comprobante'), fn ($query) => $query->where('tipo_comprobante', $request->input('tipo_comprobante')))
            ->when($request->filled('tipo_venta'), fn ($query) => $query->where('tipo_venta', $request->input('tipo_venta')))
            ->when($request->filled('cliente_id'), fn ($query) => $query->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('usuario_id'), fn ($query) => $query->where('user_id', $request->integer('usuario_id')))
            ->when($request->filled('numero_comprobante'), fn ($query) => $query->where('numero_comprobante', 'like', '%'.$request->input('numero_comprobante').'%'))
            ->when($request->filled('metodo_pago'), fn ($query) => $query->whereHas('pagos', fn ($pago) => $pago->where('metodo_pago', $request->input('metodo_pago'))))
            ->when($request->filled('cliente'), function ($query) use ($request) {
                $buscar = '%'.$request->input('cliente').'%';
                $query->whereHas('cliente', fn ($cliente) => $cliente
                    ->where('nombres', 'like', $buscar)
                    ->orWhere('razon_social', 'like', $buscar)
                    ->orWhere('numero_documento', 'like', $buscar));
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return VentaResource::collection($ventas);
    }

    public function store(StoreVentaRequest $request)
    {
        $venta = $this->ventaService->registrarVenta($this->payload($request));

        return (new VentaResource($venta))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $venta)
    {
        return new VentaResource($this->findScoped($request, $venta)->load([
            'cliente',
            'user',
            'detalles.producto',
            'detalles.presentacion.unidadMedida',
            'detalles.lote',
            'pagos',
            'comprobanteElectronico',
        ]));
    }

    public function anular(AnularVentaRequest $request, int $venta)
    {
        $venta = $this->ventaService->anularVenta($venta, $request->validated('motivo'), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]);

        return new VentaResource($venta);
    }

    public function generarTicket(Request $request, int $venta)
    {
        $this->ventaTicketService->generarTicket80($venta, $request);

        return response()->json([
            'success' => true,
            'message' => 'Ticket generado correctamente.',
        ]);
    }

    public function ticket(Request $request, int $venta)
    {
        return $this->ventaTicketService->descargarTicket80($venta, $request);
    }

    public function generarPdf(Request $request, int $venta)
    {
        $this->ventaTicketService->generarPdfA4($venta, $request);

        return response()->json([
            'success' => true,
            'message' => 'PDF generado correctamente.',
        ]);
    }

    public function pdf(Request $request, int $venta)
    {
        return $this->ventaTicketService->descargarPdfA4($venta, $request);
    }

    protected function payload(Request $request): array
    {
        return array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ]);
    }

    protected function findScoped(Request $request, int $id): Venta
    {
        return Venta::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }
}