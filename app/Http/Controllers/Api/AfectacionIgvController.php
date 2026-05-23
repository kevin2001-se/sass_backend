<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AfectacionIgvResource;
use App\Models\AfectacionIgv;
use Illuminate\Http\Request;

class AfectacionIgvController extends Controller
{
    public function index(Request $request)
    {
        $afectaciones = AfectacionIgv::query()
            ->when($request->has('estado'), fn ($query) => $query->where('estado', $request->boolean('estado')))
            ->orderBy('codigo')
            ->get();

        return AfectacionIgvResource::collection($afectaciones);
    }
}
