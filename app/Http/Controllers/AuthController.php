<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserTiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly UserTiendaService $userTiendaService)
    {
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        $this->userTiendaService->activarTiendaUnicaSiAplica($user);
        $user->refresh();
        $token = $user->createToken('api-token')->plainTextToken;

        return (new UserResource($this->loadUserContext($user)))
            ->additional([
                'token' => $token,
            ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    public function me(Request $request)
    {
        $this->userTiendaService->activarTiendaUnicaSiAplica($request->user());

        return new UserResource($this->loadUserContext($request->user()->refresh()));
    }

    protected function loadUserContext(User $user): User
    {
        return $user->load([
            'tenant',
            'empresa',
            'tiendaActiva',
            'tiendasActivas',
            'role.permissions',
        ]);
    }
}
