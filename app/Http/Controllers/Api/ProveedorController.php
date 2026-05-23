<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Http\Resources\ProveedorResource;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $items = Proveedor::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($q) => $q->where('estado', $request->boolean('estado')))
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($s) => $s->where('razon_social', 'like', '%'.$request->string('buscar').'%')->orWhere('numero_documento', 'like', '%'.$request->string('buscar').'%')))
            ->orderBy('razon_social')
            ->paginate($request->integer('per_page', 15));
        return ProveedorResource::collection($items);
    }

    public function store(StoreProveedorRequest $request)
    {
        $proveedor = Proveedor::create(array_merge($request->validated(), ['tenant_id' => $request->attributes->get('tenant')->id, 'empresa_id' => $request->attributes->get('empresa')->id]));
        return (new ProveedorResource($proveedor))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $proveedor) { return new ProveedorResource($this->findScoped($request, $proveedor)); }

    public function update(UpdateProveedorRequest $request, int $proveedor)
    {
        $proveedor = $this->findScoped($request, $proveedor);
        $proveedor->update($request->validated());
        return new ProveedorResource($proveedor->refresh());
    }

    public function destroy(Request $request, int $proveedor)
    {
        $this->findScoped($request, $proveedor)->update(['estado' => false]);
        return response()->json(['message' => 'Proveedor desactivado correctamente.']);
    }

    protected function findScoped(Request $request, int $id): Proveedor
    {
        return Proveedor::where('tenant_id', $request->attributes->get('tenant')->id)->where('empresa_id', $request->attributes->get('empresa')->id)->findOrFail($id);
    }
}
