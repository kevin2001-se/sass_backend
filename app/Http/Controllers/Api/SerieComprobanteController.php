<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSerieComprobanteRequest;
use App\Http\Requests\UpdateSerieComprobanteRequest;
use App\Http\Resources\SerieComprobanteResource;
use App\Models\SerieComprobante;
use Illuminate\Http\Request;

class SerieComprobanteController extends Controller
{
    public function index(Request $request)
    {
        $series = SerieComprobante::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('tipo_comprobante'), fn ($query) => $query->where('tipo_comprobante', $request->input('tipo_comprobante')))
            ->orderBy('tipo_comprobante')
            ->orderBy('serie')
            ->paginate($request->integer('per_page', 15));

        return SerieComprobanteResource::collection($series);
    }

    public function store(StoreSerieComprobanteRequest $request)
    {
        $serie = SerieComprobante::create(array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')->id,
        ]));

        return (new SerieComprobanteResource($serie))->response()->setStatusCode(201);
    }

    public function update(UpdateSerieComprobanteRequest $request, int $serie_comprobante)
    {
        $serie = $this->findScoped($request, $serie_comprobante);
        $serie->update($request->validated());

        return new SerieComprobanteResource($serie->refresh());
    }

    public function destroy(Request $request, int $serie_comprobante)
    {
        $serie = $this->findScoped($request, $serie_comprobante);
        $serie->update(['estado' => false]);

        return response()->json(['message' => 'Serie desactivada correctamente.']);
    }

    protected function findScoped(Request $request, int $id): SerieComprobante
    {
        return SerieComprobante::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('tienda_id', $request->attributes->get('tienda')->id)
            ->findOrFail($id);
    }
}
