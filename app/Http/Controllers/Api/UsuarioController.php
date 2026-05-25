<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Services\UsuarioService;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $service) {}

    public function index(Request $request) { return UserResource::collection($this->service->listar($request)); }
    public function store(StoreUsuarioRequest $request) { return (new UserResource($this->service->crear($request, $request->validated())))->response()->setStatusCode(201); }
    public function show(Request $request, int $usuario) { return new UserResource($this->service->findScoped($request, $usuario)); }
    public function update(UpdateUsuarioRequest $request, int $usuario) { return new UserResource($this->service->actualizar($request, $this->service->findScoped($request, $usuario), $request->validated())); }
    public function destroy(Request $request, int $usuario)
    {
        $user = $this->service->findScoped($request, $usuario);
        $user->update(['estado' => false]);
        return response()->json(['message' => 'Usuario desactivado correctamente.']);
    }
}