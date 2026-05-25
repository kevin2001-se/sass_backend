<?php

namespace App\Services;

use App\Models\Tienda;
use Illuminate\Http\Request;

class TiendaService
{
    public function listar(Request $request)
    {
        return Tienda::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim($request->input('q'));
                $query->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'ILIKE', "%{$q}%")
                        ->orWhere('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('direccion', 'ILIKE', "%{$q}%");
                });
            })
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 15));
    }

    public function crear(Request $request, array $data): Tienda
    {
        return Tienda::create(array_merge($data, [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'estado' => $data['estado'] ?? true,
        ]));
    }

    public function actualizar(Tienda $tienda, array $data): Tienda
    {
        $tienda->update($data);
        return $tienda->refresh();
    }

    public function findScoped(Request $request, int $id): Tienda
    {
        return Tienda::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }
}