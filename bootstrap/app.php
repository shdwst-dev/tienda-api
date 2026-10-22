<?php

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Toda ruta /api exige la API key, antes que cualquier otro middleware del grupo.
        $middleware->api(prepend: [ValidateApiKey::class]);

        // Límite general "api" en todas las rutas /api (login y pedidos tienen el suyo, ver routes/api.php).
        $middleware->throttleApi();

        // Orden final: API key -> rate limiter -> JWT -> route model binding. El límite va antes del JWT
        // para que también cuenten las peticiones sin token o con token malo (se cuentan por API key).
        $middleware->appendToPriorityList(after: ThrottleRequests::class, append: AuthenticateJwt::class);

        // Se usan por ruta: ->middleware('jwt') y ->middleware('rol:admin')
        $middleware->alias([
            'jwt' => AuthenticateJwt::class,
            'rol' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // En /api los errores HTTP (403, 404, 405...) salen como JSON corto,
        // sin trace ni rutas del servidor aunque APP_DEBUG=true.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $mensaje = $e->getStatusCode() === 404 ? 'Recurso no encontrado.' : $e->getMessage();

            return response()->json(['message' => $mensaje], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
