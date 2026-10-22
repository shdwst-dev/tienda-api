<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Valida las credenciales y regresa el usuario (todavía sin token).
     */
    public function login(Request $request): JsonResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credenciales['email'])->first();

        if (! $user || ! Hash::check($credenciales['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return response()->json(['user' => $user]);
    }

    /**
     * VULNERABLE (v1): el id del usuario viene en la URL y nadie verifica
     * que quien pregunta sea ese usuario, así que se puede consultar cualquier perfil.
     */
    public function perfil(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(User::findOrFail($datos['user_id']));
    }
}
