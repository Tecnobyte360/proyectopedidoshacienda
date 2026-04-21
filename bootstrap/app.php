<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key'    => \App\Http\Middleware\ApiKeyAuth::class,
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant'     => \App\Http\Middleware\SetCurrentTenant::class,
            'no_super_sin_imp' => \App\Http\Middleware\BloquearSuperAdminSinImpersonar::class,
            'solo_principal'   => \App\Http\Middleware\SoloDominioPrincipal::class,
        ]);

        // Si no está autenticado y golpea una ruta protegida, redirigir al login
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Multi-tenant: setea tenant actual en cada request web
        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Cuando un usuario autenticado no tiene el permiso, mostrar pantalla 403
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes permisos para esta acción.'], 403);
            }
            return response()->view('errors.403', ['mensaje' => $e->getMessage()], 403);
        });
    })->create();
