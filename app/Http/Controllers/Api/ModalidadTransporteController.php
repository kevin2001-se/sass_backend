<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModalidadTransporteResource;
use App\Models\ModalidadTransporte;

class ModalidadTransporteController extends Controller
{
    public function index()
    {
        $modalidades = ModalidadTransporte::where('estado', true)
            ->orderBy('codigo')
            ->get();

        return ModalidadTransporteResource::collection($modalidades);
    }
}