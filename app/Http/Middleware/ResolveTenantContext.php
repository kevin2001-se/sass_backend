<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $user->tenant;
        $empresa = $user->empresa;

        if (! $tenant || ! $empresa) {
            return new JsonResponse(['message' => 'Tenant o empresa no configurados para el usuario.'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $empresa->tenant_id !== (int) $tenant->id) {
            return new JsonResponse(['message' => 'Empresa invÃ¡lida para el tenant del usuario.'], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('empresa', $empresa);

        return $next($request);
    }
}
