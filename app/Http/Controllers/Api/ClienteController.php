<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(function ($subquery) use ($buscar) {
                    $subquery->where('nombres', 'like', '%'.$buscar.'%')
                        ->orWhere('razon_social', 'like', '%'.$buscar.'%')
                        ->orWhere('numero_documento', 'like', '%'.$buscar.'%');
                });
            })
            ->orderBy('nombres')
            ->paginate($request->integer('per_page', 15));

        return ClienteResource::collection($clientes);
    }

    public function store(StoreClienteRequest $request)
    {
        $cliente = Cliente::create(array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
        ]));

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $cliente)
    {
        return new ClienteResource($this->findScoped($request, $cliente));
    }

    public function update(UpdateClienteRequest $request, int $cliente)
    {
        $cliente = $this->findScoped($request, $cliente);
        $cliente->update($request->validated());

        return new ClienteResource($cliente->refresh());
    }

    public function destroy(Request $request, int $cliente)
    {
        $cliente = $this->findScoped($request, $cliente);
        $cliente->update(['estado' => false]);

        return response()->json(['message' => 'Cliente desactivado correctamente.']);
    }

    protected function findScoped(Request $request, int $id): Cliente
    {
        return Cliente::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }
}
