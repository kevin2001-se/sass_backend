<?php

namespace App\Services;

use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserTiendaService
{
    public function obtenerTiendasUsuario(User $user): Collection
    {
        return $user->tiendasActivas()
            ->wherePivot('tenant_id', $user->tenant_id)
            ->wherePivot('empresa_id', $user->empresa_id)
            ->orderBy('nombre')
            ->get();
    }

    public function seleccionarTienda(User $user, int $tiendaId): Tienda
    {
        $tienda = $this->validarAccesoTienda($user, $tiendaId);
        $user->forceFill(['tienda_activa_id' => $tienda->id])->save();

        return $tienda;
    }

    public function validarAccesoTienda(User $user, int $tiendaId): Tienda
    {
        $tienda = $user->tiendasActivas()
            ->where('tiendas.id', $tiendaId)
            ->where('tiendas.tenant_id', $user->tenant_id)
            ->where('tiendas.empresa_id', $user->empresa_id)
            ->first();

        if (! $tienda) {
            throw ValidationException::withMessages([
                'tienda_id' => ['La tienda no estÃ¡ asignada al usuario o no pertenece a su empresa.'],
            ]);
        }

        return $tienda;
    }

    public function asignarTienda(User $user, int $tiendaId): Tienda
    {
        $tienda = Tienda::where('tenant_id', $user->tenant_id)
            ->where('empresa_id', $user->empresa_id)
            ->where('estado', true)
            ->findOrFail($tiendaId);

        $user->tiendas()->syncWithoutDetaching([
            $tienda->id => [
                'tenant_id' => $user->tenant_id,
                'empresa_id' => $user->empresa_id,
                'estado' => true,
            ],
        ]);

        if (! $user->tienda_activa_id && $this->obtenerTiendasUsuario($user)->count() === 1) {
            $this->seleccionarTienda($user, $tienda->id);
        }

        return $tienda;
    }

    public function quitarTienda(User $user, int $tiendaId): void
    {
        $user->tiendas()->updateExistingPivot($tiendaId, ['estado' => false]);

        if ((int) $user->tienda_activa_id === $tiendaId) {
            $user->forceFill(['tienda_activa_id' => null])->save();
        }
    }

    public function activarTiendaUnicaSiAplica(User $user): ?Tienda
    {
        if ($user->tienda_activa_id) {
            try {
                return $this->validarAccesoTienda($user, (int) $user->tienda_activa_id);
            } catch (ValidationException) {
                $user->forceFill(['tienda_activa_id' => null])->save();
            }
        }

        $tiendas = $this->obtenerTiendasUsuario($user);

        if ($tiendas->count() === 1) {
            return $this->seleccionarTienda($user, $tiendas->first()->id);
        }

        return null;
    }
}
