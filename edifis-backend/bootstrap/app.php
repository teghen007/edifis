<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        if (env('EDIFIS_MODE', 'local') === 'cloud') {
            $middleware->api(prepend: [
                \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e) {
            if (! request()->expectsJson()) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'code' => 'validation_failed',
                    'message' => $e->getMessage(),
                    'details' => $e->errors(),
                    'retry_after_seconds' => null,
                ], 422);
            }

            // No / invalid token: return a clean 401 so clients can re-login,
            // instead of falling through to the generic 500 below. (This custom
            // renderer shadows Laravel's built-in unauthenticated() handler.)
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'code' => 'unauthenticated',
                    'message' => 'Unauthenticated.',
                    'details' => null,
                    'retry_after_seconds' => null,
                ], 401);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $statusCode = $e->getStatusCode();
                $code = match ($statusCode) {
                    401 => 'unauthenticated',
                    403 => 'forbidden',
                    404 => 'not_found',
                    422 => 'validation_failed',
                    429 => 'rate_limited',
                    default => 'server_error',
                };

                $response = response()->json([
                    'code' => $code,
                    'message' => $e->getMessage(),
                    'details' => null,
                    'retry_after_seconds' => null,
                ], $statusCode);

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getHeaders()) {
                    foreach ($e->getHeaders() as $key => $value) {
                        $response->header($key, $value);
                    }
                }

                return $response;
            }

            return response()->json([
                'code' => 'server_error',
                'message' => app()->isProduction() ? 'Internal server error' : $e->getMessage(),
                'details' => null,
                'retry_after_seconds' => null,
            ], 500);
        });
    })
    ->create();
