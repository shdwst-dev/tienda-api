<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Valida las credenciales y regresa un JWT firmado junto con el usuario.
     */
    public function login(Request $request, JwtService $jwt): JsonResponse
    {
        $this->normalizarEmail($request);

        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credenciales['email'])->first();

        if (! $user || ! Hash::check($credenciales['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return response()->json($this->sesion($user, $jwt));
    }

    /**
     * Crea una cuenta de cliente y la deja con la sesión iniciada (mismo formato que el login).
     */
    public function register(Request $request, JwtService $jwt): JsonResponse
    {
        $this->normalizarEmail($request);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        // forceCreate porque "rol" no es asignable en masa. El rol de una cuenta nueva siempre es cliente:
        // nunca se toma de la petición. El cast "hashed" de User cifra la contraseña.
        $user = User::forceCreate([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => User::ROL_CLIENTE,
        ]);

        return response()->json($this->sesion($user, $jwt), 201);
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

    /**
     * @return array{token: string, token_type: string, expires_in: int, user: User}
     */
    private function sesion(User $user, JwtService $jwt): array
    {
        $emitido = $jwt->emitir($user);

        return [
            'token' => $emitido['token'],
            'token_type' => 'Bearer',
            'expires_in' => $emitido['expires_in'],
            'user' => $user,
        ];
    }

    /**
     * Los emails se guardan y se buscan en minúsculas, así "Ana@x.com" y "ana@x.com" son la misma cuenta.
     */
    private function normalizarEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
