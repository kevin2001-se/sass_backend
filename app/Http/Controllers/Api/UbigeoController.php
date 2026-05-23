<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartamentoResource;
use App\Http\Resources\DistritoResource;
use App\Http\Resources\ProvinciaResource;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Provincia;
use Illuminate\Http\Request;

class UbigeoController extends Controller
{
    public function departamentos()
    {
        return DepartamentoResource::collection(
            Departamento::where('estado', true)->orderBy('nombre')->get()
        );
    }

    public function provincias(Request $request)
    {
        $request->validate(['departamento_id' => ['required', 'integer', 'exists:departamentos,id']]);

        return ProvinciaResource::collection(
            Provincia::where('estado', true)
                ->where('departamento_id', $request->integer('departamento_id'))
                ->orderBy('nombre')
                ->get()
        );
    }

    public function distritos(Request $request)
    {
        $request->validate(['provincia_id' => ['required', 'integer', 'exists:provincias,id']]);

        return DistritoResource::collection(
            Distrito::where('estado', true)
                ->where('provincia_id', $request->integer('provincia_id'))
                ->orderBy('nombre')
                ->get()
        );
    }

    public function buscarDistritos(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return DistritoResource::collection(
            Distrito::with('provincia.departamento')
                ->where('estado', true)
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($subquery) use ($q) {
                        $subquery->where('ubigeo', 'ILIKE', "%{$q}%")
                            ->orWhere('nombre', 'ILIKE', "%{$q}%")
                            ->orWhereHas('provincia', fn ($provincia) => $provincia->where('nombre', 'ILIKE', "%{$q}%"))
                            ->orWhereHas('provincia.departamento', fn ($departamento) => $departamento->where('nombre', 'ILIKE', "%{$q}%"));
                    });
                })
                ->orderBy('nombre')
                ->limit(20)
                ->get()
        );
    }
}
