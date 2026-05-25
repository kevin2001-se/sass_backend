<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RolePermissionService $service) {}

    public function index(Request $request) { return RoleResource::collection($this->service->listarRoles($request)); }
    public function store(StoreRoleRequest $request) { return (new RoleResource($this->service->crear($request, $request->validated())))->response()->setStatusCode(201); }
    public function show(Request $request, int $role) { return new RoleResource($this->service->findScoped($request, $role)); }
    public function update(UpdateRoleRequest $request, int $role) { return new RoleResource($this->service->actualizar($this->service->findScoped($request, $role), $request->validated())); }
    public function destroy(Request $request, int $role)
    {
        $model = $this->service->findScoped($request, $role);
        $model->update(['active' => false]);
        return response()->json(['message' => 'Rol desactivado correctamente.']);
    }
}