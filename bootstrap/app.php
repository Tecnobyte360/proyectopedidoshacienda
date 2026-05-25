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
        // 🔐 Trust the reverse proxy (nginx) so Laravel honors X-Forwarded-Proto
        // y X-Forwarded-For. Sin esto, las signed URLs de Livewire fallan con
        // 401 (porque la firma se genera con https:// pero el request se ve
        // como http:// internamente).
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'api.key'    => \App\Http\Middleware\ApiKeyAuth::class,
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant'     => \App\Http\Middleware\SetCurrentTenant::class,
            'no_super_sin_imp' => \App\Http\Middleware\BloquearSuperAdminSinImpersonar::class,
            'solo_principal'   => \App\Http\Middleware\SoloDominioPrincipal::class,
            'whatsapp.webhook' => \App\Http\Middleware\VerificarWebhookWhatsapp::class,
            'bloquea_moroso'   => \App\Http\Middleware\BloqueaSiMoroso::class,
        ]);

        // Si no está autenticado y golpea una ruta protegida, redirigir al login
        // ⚠️ Importante: usar '/login' (path relativo) en vez de route('login') porque
        // route() genera URLs absolutas con APP_URL — eso enviaría al usuario a
        // admin.kivox.co/login aunque esté en la-hacienda.kivox.co. El path relativo
        // mantiene el host actual y respeta el subdominio del tenant.
        $middleware->redirectGuestsTo('/login');

        // Multi-tenant: setea tenant actual en cada request web
        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentTenant::class,
            // 🚫 Bloqueo soft por mora: si tenant.suspendido_por_mora=true, redirige
            // a /billing/expirado (excepto rutas whitelist: login, logout, billing/*).
            \App\Http\Middleware\BloqueaSiMoroso::class,
            // 🔐 Si tenant.requiere_2fa y el user no lo tiene, redirige a
            // /perfil/seguridad tras período de gracia.
            \App\Http\Middleware\Forzar2FA::class,
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
