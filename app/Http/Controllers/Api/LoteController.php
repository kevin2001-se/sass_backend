<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoteRequest;
use App\Http\Requests\UpdateLoteRequest;
use App\Http\Resources\LoteResource;
use App\Models\Lote;
use App\Services\InventarioCargaMasivaService;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    public function __construct(private readonly InventarioCargaMasivaService $cargaMasivaService)
    {
    }

    public function index(Request $request)
    {
        $lotes = Lote::with(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'stocks' => fn ($query) => $query->where('tenant_id', $request->attributes->get('tenant')->id)->where('empresa_id', $request->attributes->get('empresa')->id)->where('tienda_id', $request->attributes->get('tienda')?->id)])
            ->where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->when($request->filled('producto_id'), fn ($query) => $query->where('producto_id', $request->integer('producto_id')))
            ->when($request->filled('buscar'), fn ($query) => $query->where('codigo_lote', 'ilike', '%'.$request->string('buscar').'%'))
            ->orderBy('fecha_vencimiento')
            ->orderBy('codigo_lote')
            ->paginate($request->integer('per_page', 15));

        return LoteResource::collection($lotes);
    }

    public function store(StoreLoteRequest $request)
    {
        $lote = Lote::create(array_merge($request->validated(), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
        ]));

        return (new LoteResource($lote->load(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'stocks'])))->response()->setStatusCode(201);
    }



    public function plantillaCargaMasiva(Request $request)
    {
        return $this->cargaMasivaService->plantilla('lotes', [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')?->id,
            'user_id' => $request->user()->id,
        ]);
    }
    public function cargaMasiva(Request $request)
    {
        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $resultado = $this->cargaMasivaService->importarLotes($request->file('archivo'), [
            'tenant_id' => $request->attributes->get('tenant')->id,
            'empresa_id' => $request->attributes->get('empresa')->id,
            'tienda_id' => $request->attributes->get('tienda')?->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => $resultado['total_errores'] === 0,
            'message' => $resultado['total_errores'] > 0 ? 'Carga masiva de lotes procesada con observaciones.' : 'Carga masiva de lotes procesada correctamente.',
            'data' => $resultado,
        ]);
    }
    public function show(Request $request, int $lote)
    {
        return new LoteResource($this->findScoped($request, $lote)->load(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'stocks']));
    }

    public function update(UpdateLoteRequest $request, int $lote)
    {
        $lote = $this->findScoped($request, $lote);
        $lote->update($request->validated());

        return new LoteResource($lote->refresh()->load(['producto.categoria', 'producto.presentacionPrincipal.unidadMedida', 'stocks']));
    }

    public function destroy(Request $request, int $lote)
    {
        $lote = $this->findScoped($request, $lote);
        $lote->update(['estado' => false]);

        return response()->json(['message' => 'Lote desactivado correctamente.']);
    }

    protected function findScoped(Request $request, int $id): Lote
    {
        return Lote::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }
}
