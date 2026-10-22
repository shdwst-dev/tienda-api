<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;
use Throwable;

class JwtService
{
    private const ALGORITMO = 'HS256';

    private string $secreto;

    public function __construct()
    {
        $secreto = (string) config('jwt.secret');

        if (strlen($secreto) < 32) {
            throw new RuntimeException('JWT_SECRET no está configurado o tiene menos de 32 caracteres.');
        }

        $this->secreto = $secreto;
    }

    /**
     * Firma un token para el usuario con los claims sub, rol, iat, exp y jti.
     *
     * @return array{token: string, expires_in: int}
     */
    public function emitir(User $user): array
    {
        $ahora = now()->getTimestamp();
        $vida = max(1, (int) config('jwt.ttl')) * 60;

        $claims = [
            'sub' => (string) $user->id,
            'rol' => $user->rol,
            'iat' => $ahora,
            'exp' => $ahora + $vida,
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => JWT::encode($claims, $this->secreto, self::ALGORITMO),
            'expires_in' => $vida,
        ];
    }

    /**
     * Verifica firma y vigencia y regresa los claims. El algoritmo lo fija el servidor
     * (no el header del token), así que "alg: none" o cambiar de algoritmo no sirve.
     *
     * @throws Throwable si el token está expirado, mal firmado o mal formado
     */
    public function decodificar(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secreto, self::ALGORITMO));
    }

    /**
     * Id del dueño de un token válido (firma y vigencia), o null si no hay token o no sirve.
     * No consulta la base de datos ni la lista negra: solo sirve para identificar quién pide,
     * por ejemplo como llave del rate limiter. La autenticación real la hace AuthenticateJwt.
     */
    public function usuarioDeToken(?string $token): ?int
    {
        if (! $token) {
            return null;
        }

        try {
            $claims = $this->decodificar($token);
        } catch (Throwable) {
            return null;
        }

        return isset($claims->sub) && ctype_digit((string) $claims->sub) ? (int) $claims->sub : null;
    }

    /**
     * Mete el jti en la lista negra hasta el momento en que el token expiraría de todos modos.
     */
    public function revocar(string $jti, int $exp): void
    {
        Cache::put($this->llaveRevocado($jti), true, max(1, $exp - now()->getTimestamp()));
    }

    public function estaRevocado(string $jti): bool
    {
        return Cache::has($this->llaveRevocado($jti));
    }

    private function llaveRevocado(string $jti): string
    {
        return 'jwt:revocado:'.$jti;
    }
}
