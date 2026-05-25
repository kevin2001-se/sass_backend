<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorService
{
    public function listar(Request $request)
    {
        $search = trim((string) ($request->input('search') ?? $request->input('buscar') ?? ''));

        return Proveedor::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('tipo_documento'), fn ($query) => $query->where('tipo_documento', $request->input('tipo_documento')))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('numero_documento', 'ILIKE', "%{$search}%")
                        ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                        ->orWhere('contacto', 'ILIKE', "%{$search}%")
                        ->orWhere('telefono', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('razon_social')
            ->paginate($request->integer('per_page', 15));
    }

    public function crear(Request $request, array $data): Proveedor
    {
        return Proveedor::create(array_merge($data, [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'estado' => $data['estado'] ?? true,
        ]));
    }

    public function actualizar(Proveedor $proveedor, array $data): Proveedor
    {
        $proveedor->update($data);
        return $proveedor->refresh();
    }

    public function findScoped(Request $request, int $id): Proveedor
    {
        return Proveedor::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }
}