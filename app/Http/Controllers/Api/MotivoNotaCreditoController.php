<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MotivoNotaCreditoResource;
use App\Models\MotivoNotaCredito;

class MotivoNotaCreditoController extends Controller
{
    public function index()
    {
        return MotivoNotaCreditoResource::collection(
            MotivoNotaCredito::where('estado', true)->orderBy('codigo')->get()
        );
    }
}
