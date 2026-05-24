<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MotivoNotaDebitoResource;
use App\Models\MotivoNotaDebito;

class MotivoNotaDebitoController extends Controller
{
    public function index()
    {
        return MotivoNotaDebitoResource::collection(
            MotivoNotaDebito::where('estado', true)->orderBy('codigo')->get()
        );
    }
}