<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Valida las credenciales y regresa un JWT firmado junto con el usuario.
     */
    public function login(Request $request, JwtService $jwt): JsonResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credenciales['email'])->first();

        if (! $user || ! Hash::check($credenciales['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $emitido = $jwt->emitir($user);

        return response()->json([
            'token' => $emitido['token'],
            'token_type' => 'Bearer',
            'expires_in' => $emitido['expires_in'],
            'user' => $user,
        ]);
    }

    /**
     * Revoca el token que se usó en esta petición: su jti entra a la lista negra
     * hasta que el token habría expirado. Otros tokens del mismo usuario siguen vivos.
     */
    public function logout(Request $request, JwtService $jwt): JsonResponse
    {
        $claims = $request->attributes->get('jwt');

        $jwt->revocar($claims->jti, (int) $claims->exp);

        return response()->json(['message' => 'Sesión cerrada: el token quedó revocado.']);
    }

    /**
     * El perfil es siempre el del dueño del token; ya no se recibe ningún user_id.
     */
    public function perfil(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
