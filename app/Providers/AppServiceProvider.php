<?php

namespace App\Providers;

use App\Services\HostingerDnsService;
use App\Services\TenantManager;
use App\Services\WhatsappResolverService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 🔥 CRÍTICO: TenantManager DEBE ser singleton.
        // Si no, cada app(TenantManager::class) crea una instancia nueva
        // y el set() del middleware no es visible para los componentes Livewire.
        $this->app->singleton(TenantManager::class);

        // Mismo razonamiento para los demás servicios con caché en memoria
        $this->app->singleton(WhatsappResolverService::class);
        $this->app->singleton(HostingerDnsService::class);
    }

    /**
     * Bootstrap any application services.
     */
   public function boot(): void
{
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }

    // 🔓 Super-admin tiene acceso a TODO automáticamente.
    // Bypassa cualquier chequeo de permisos. Hacemos query directa a BD
    // (no Spatie cache) para que sea robusto frente a cambios de tenant
    // contexto y cache invalidations.
    Gate::before(function ($user, $ability) {
        if (!$user) return null;

        // Cachear el resultado en memoria por request para no consultar BD
        // en cada chequeo (puede haber decenas en una sola request).
        static $esSuperPorUsuario = [];
        $key = $user->id;

        if (!array_key_exists($key, $esSuperPorUsuario)) {
            try {
                $esSuperPorUsuario[$key] = \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('model_has_roles.model_type', get_class($user))
                    ->where('roles.name', 'super-admin')
                    ->exists();
            } catch (\Throwable $e) {
                $esSuperPorUsuario[$key] = false;
            }
        }

        return $esSuperPorUsuario[$key] ? true : null;
    });
}
}
