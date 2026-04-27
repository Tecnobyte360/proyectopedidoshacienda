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
    // Devuelve true si el usuario tiene rol 'super-admin' — bypassa cualquier
    // chequeo de permisos. Cualquier permiso que se agregue al sistema
    // queda accesible para super-admin sin necesidad de re-seedear.
    Gate::before(function ($user, $ability) {
        try {
            return $user?->hasRole('super-admin') ? true : null;
        } catch (\Throwable $e) {
            return null;
        }
    });
}
}
