<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnidadMedidaSunatResource;
use App\Models\UnidadMedidaSunat;

class UnidadMedidaSunatController extends Controller
{
    public function index()
    {
        $unidades = UnidadMedidaSunat::where('estado', true)
            ->orderBy('codigo')
            ->get();

        return UnidadMedidaSunatResource::collection($unidades);
    }
}