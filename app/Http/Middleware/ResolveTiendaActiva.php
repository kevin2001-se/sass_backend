<?php

namespace App\Http\Middleware;

use App\Services\UserTiendaService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ResolveTiendaActiva
{
    public function __construct(private readonly UserTiendaService $userTiendaService)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->userTiendaService->activarTiendaUnicaSiAplica($user);
        $user->refresh();

        if (! $user->tienda_activa_id) {
            return new JsonResponse(['message' => 'Debe seleccionar una tienda activa.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $tienda = $this->userTiendaService->validarAccesoTienda($user, (int) $user->tienda_activa_id);
        } catch (ValidationException) {
            return new JsonResponse(['message' => 'Debe seleccionar una tienda activa.'], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('tienda', $tienda);

        return $next($request);
    }
}
