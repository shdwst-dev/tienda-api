<?php

namespace App\Providers;

use App\Services\JwtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ningún límite usa la IP: detrás de Cloudflare Tunnel todas las peticiones llegan desde 127.0.0.1.

        // POST /api/login: 5 por minuto por email + API key.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return $this->limite(5, 'Demasiados intentos de inicio de sesión.', $email.'|'.$request->header('X-API-KEY'));
        });

        // Resto de la API: 60 por minuto por usuario del token (o por API key si no hay token válido).
        RateLimiter::for('api', function (Request $request) {
            return $this->limite(60, 'Demasiadas peticiones.', $this->usuarioOApiKey($request));
        });

        // POST /api/pedidos: 3 por minuto por usuario.
        RateLimiter::for('pedidos', function (Request $request) {
            return $this->limite(3, 'Demasiados pedidos seguidos.', $this->usuarioOApiKey($request));
        });
    }

    /**
     * Límite por minuto cuya respuesta 429 es JSON e incluye Retry-After (y los X-RateLimit-*).
     */
    private function limite(int $porMinuto, string $mensaje, string $llave): Limit
    {
        return Limit::perMinute($porMinuto)->by($llave)->response(function (Request $request, array $headers) use ($mensaje) {
            $segundos = (int) ($headers['Retry-After'] ?? 60);

            return response()->json([
                'message' => "{$mensaje} Intenta de nuevo en {$segundos} segundos.",
                'retry_after' => $segundos,
            ], 429, $headers);
        });
    }

    /**
     * Llave del límite: el usuario dueño del token (con firma y vigencia verificadas, así un token falso
     * no puede gastar la cuota de otra persona) o, si no hay token válido, la API key.
     */
    private function usuarioOApiKey(Request $request): string
    {
        $userId = $request->bearerToken()
            ? app(JwtService::class)->usuarioDeToken($request->bearerToken())
            : null;

        return $userId ? 'user:'.$userId : 'key:'.$request->header('X-API-KEY');
    }
}
