<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmpresaConfiguracionRequest;
use App\Http\Resources\EmpresaConfiguracionResource;
use App\Services\EmpresaConfiguracionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ConfiguracionEmpresaController extends Controller
{
    public function __construct(private EmpresaConfiguracionService $service) {}

    public function show(Request $request)
    {
        return new EmpresaConfiguracionResource($request->attributes->get('empresa'));
    }

    public function update(UpdateEmpresaConfiguracionRequest $request)
    {
        $empresa = $this->service->actualizar($request->attributes->get('empresa'), $request->validated());
        return new EmpresaConfiguracionResource($empresa);
    }

    public function logo(Request $request)
    {
        $empresa = $request->attributes->get('empresa');

        if (! $empresa->logo_path || ! Storage::disk('local')->exists($empresa->logo_path)) {
            abort(Response::HTTP_NOT_FOUND, 'Logo no encontrado.');
        }

        return Storage::disk('local')->download($empresa->logo_path);
    }
}