<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    /**
     * Valida el header "Authorization: Bearer <token>": firma, expiración y lista negra.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->rechazar('Falta el token (header Authorization: Bearer <token>).');
        }

        try {
            $claims = $this->jwt->decodificar($token);
        } catch (ExpiredException) {
            return $this->rechazar('Token expirado.');
        } catch (Throwable) {
            // Firma incorrecta, formato roto, algoritmo no permitido, iat/nbf en el futuro...
            return $this->rechazar('Token inválido.');
        }

        // La librería acepta tokens sin exp; aquí sub, jti y exp son obligatorios.
        if (! isset($claims->sub, $claims->jti, $claims->exp) || ! ctype_digit((string) $claims->sub)) {
            return $this->rechazar('Token inválido.');
        }

        if ($this->jwt->estaRevocado($claims->jti)) {
            return $this->rechazar('Token revocado.');
        }

        $user = User::find($claims->sub);

        if (! $user) {
            return $this->rechazar('Token inválido.');
        }

        Auth::setUser($user);
        $request->attributes->set('jwt', $claims);

        return $next($request);
    }

    private function rechazar(string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
