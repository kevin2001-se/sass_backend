<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSunatConfiguracionRequest;
use App\Http\Requests\UpdateSunatConfiguracionRequest;
use App\Http\Resources\SunatConfiguracionResource;
use App\Models\SunatConfiguracion;
use App\Services\Sunat\SunatConfiguracionService;
use App\Services\Sunat\GreSunatClientFactory;
use Throwable;
use Illuminate\Http\Request;

class SunatConfiguracionController extends Controller
{
    public function __construct(private readonly SunatConfiguracionService $service, private readonly GreSunatClientFactory $greFactory)
    {
    }

    public function show(Request $request)
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('estado', true)
            ->first();

        if (! $configuracion) {
            return response()->json(['message' => 'No existe configuracion SUNAT activa para esta empresa.'], 404);
        }

        return new SunatConfiguracionResource($configuracion);
    }

    public function store(StoreSunatConfiguracionRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->attributes->get('tenant')->id;
        $data['empresa_id'] = $request->attributes->get('empresa')->id;

        if ($request->hasFile('certificado')) {
            $data['certificado'] = $request->file('certificado');
        }

        $configuracion = $this->service->crear($data);

        return (new SunatConfiguracionResource($configuracion))->response()->setStatusCode(201);
    }

    public function update(UpdateSunatConfiguracionRequest $request, int $configuracion)
    {
        $configuracion = $this->findScoped($request, $configuracion);
        $data = $request->validated();

        if ($request->hasFile('certificado')) {
            $data['certificado'] = $request->file('certificado');
        }

        return new SunatConfiguracionResource($this->service->actualizar($configuracion, $data));
    }

    public function destroy(Request $request, int $configuracion)
    {
        $configuracion = $this->findScoped($request, $configuracion);
        $this->service->desactivar($configuracion);

        return response()->json(['message' => 'Configuracion SUNAT desactivada correctamente.']);
    }

    public function probarGre(Request $request)
    {
        $configuracion = SunatConfiguracion::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->where('estado', true)
            ->firstOrFail();

        try {
            $this->service->validarGre($configuracion);
            $this->greFactory->probarAutorizacion($configuracion);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuracion GRE invalida.',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuracion GRE valida y autorizada.',
            'data' => [
                'ambiente' => $configuracion->ambiente,
                'gre_habilitado' => $configuracion->greHabilitado(),
                'tiene_gre_credenciales' => $configuracion->tieneGreCredenciales(),
            ],
        ]);
    }

    protected function findScoped(Request $request, int $id): SunatConfiguracion
    {
        return SunatConfiguracion::where('tenant_id', $request->attributes->get('tenant')->id)
            ->where('empresa_id', $request->attributes->get('empresa')->id)
            ->findOrFail($id);
    }
}