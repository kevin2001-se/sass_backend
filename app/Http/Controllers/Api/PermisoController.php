<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Services\RolePermissionService;

class PermisoController extends Controller
{
    public function __construct(private RolePermissionService $service) {}

    public function index()
    {
        return PermissionResource::collection($this->service->listarPermisos());
    }
}