<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MotivoTrasladoResource;
use App\Models\MotivoTraslado;

class MotivoTrasladoController extends Controller
{
    public function index()
    {
        $motivos = MotivoTraslado::where('estado', true)
            ->orderBy('codigo')
            ->get();

        return MotivoTrasladoResource::collection($motivos);
    }
}