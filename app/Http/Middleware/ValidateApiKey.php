<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Exige el header X-API-KEY y lo compara con API_KEY del .env.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $esperada = (string) config('services.api_key');

        // Sin API_KEY en el servidor no se deja pasar a nadie (nunca "abierto por omisión").
        if ($esperada === '') {
            return response()->json(['message' => 'La API key no está configurada en el servidor.'], 500);
        }

        $recibida = $request->header('X-API-KEY');

        // hash_equals compara en tiempo constante para no filtrar la llave por tiempos de respuesta.
        if (! is_string($recibida) || ! hash_equals($esperada, $recibida)) {
            return response()->json(['message' => 'API key ausente o inválida.'], 401);
        }

        return $next($request);
    }
}
