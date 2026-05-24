<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotasDebito\AnularNotaDebitoRequest;
use App\Http\Requests\NotasDebito\StoreNotaDebitoRequest;
use App\Http\Resources\NotaDebitoResource;
use App\Models\NotaDebito;
use App\Services\NotaDebitoService;
use Illuminate\Http\Request;

class NotaDebitoController extends Controller
{
    public function __construct(private readonly NotaDebitoService $service)
    {
    }

    public function index(Request $request)
    {
        return NotaDebitoResource::collection($this->service->listar($request->query(), $this->scope($request)));
    }

    public function store(StoreNotaDebitoRequest $request)
    {
        $nota = $this->service->crear($request->validated(), $this->scope($request));

        return (new NotaDebitoResource($nota))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id)
    {
        return new NotaDebitoResource($this->service->obtener($id, $this->scope($request)));
    }

    public function anular(AnularNotaDebitoRequest $request, NotaDebito $notaDebito)
    {
        return new NotaDebitoResource($this->service->anular($notaDebito, $request->validated('motivo'), $this->scope($request)));
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