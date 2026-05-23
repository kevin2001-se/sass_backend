<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $role = $user->role;

        if (! $role) {
            return new JsonResponse(['message' => 'El usuario no tiene un rol asignado.'], Response::HTTP_FORBIDDEN);
        }

        if ($role->empresa_id !== $user->empresa_id) {
            return new JsonResponse(['message' => 'Rol inválido para la empresa del usuario.'], Response::HTTP_FORBIDDEN);
        }

        $hasPermission = $role->permissions()->where('name', $permission)->exists();

        if (! $hasPermission) {
            return new JsonResponse(['message' => 'No tiene permiso para esta acción.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
