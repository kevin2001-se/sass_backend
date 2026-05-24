<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotasCredito\AnularNotaCreditoRequest;
use App\Http\Requests\NotasCredito\StoreNotaCreditoRequest;
use App\Http\Resources\NotaCreditoResource;
use App\Models\NotaCredito;
use App\Services\NotaCreditoService;
use Illuminate\Http\Request;

class NotaCreditoController extends Controller
{
    public function __construct(private readonly NotaCreditoService $service)
    {
    }

    public function index(Request $request)
    {
        return NotaCreditoResource::collection(
            $this->service->listar($request->query(), $this->scope($request))
        );
    }

    public function store(StoreNotaCreditoRequest $request)
    {
        $nota = $this->service->crear($request->validated(), $this->scope($request));

        return (new NotaCreditoResource($nota))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id)
    {
        return new NotaCreditoResource($this->service->obtener($id, $this->scope($request)));
    }

    public function aplicarEfectos(Request $request, NotaCredito $notaCredito)
    {
        $data = $request->validate([
            'metodo_pago_devolucion' => ['nullable', 'in:EFECTIVO,YAPE,PLIN,TARJETA,TRANSFERENCIA'],
            'observacion_caja' => ['nullable', 'string', 'max:500'],
        ]);

        return new NotaCreditoResource(
            $this->service->aplicarEfectosPendientes($notaCredito, $data, $this->scope($request))
        );
    }
    public function anular(AnularNotaCreditoRequest $request, NotaCredito $notaCredito)
    {
        return new NotaCreditoResource(
            $this->service->anular($notaCredito, $request->validated('motivo'), $this->scope($request))
        );
    }

    protected function scope(Request $request): array
    {
        return [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
            'user_id' => $request->user()->id,
        ];
    }
}

