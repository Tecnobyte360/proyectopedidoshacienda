<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\HostingerDnsService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * ELIMINA un tenant DEFINITIVAMENTE de la plataforma.
 *
 * Operaciones:
 *  1. Borra el registro DNS A en Hostinger
 *  2. Encola un .conf.delete en storage/app/nginx-tenants/ para que el
 *     watcher del host borre el sites-enabled + cert SSL + recargue Nginx
 *  3. Elimina (cascade) todos los datos del tenant en la BD:
 *     pedidos, productos, clientes, sedes, users, suscripciones, pagos, etc.
 *  4. Hace soft-delete del propio tenant (queda en deleted_at)
 *
 * ⚠️ NO HAY VUELTA ATRÁS sin restore manual desde backup.
 *
 * Uso:
 *   php artisan tenants:eliminar-definitivo la-hacienda
 *   php artisan tenants:eliminar-definitivo la-hacienda --force  (sin confirmación)
 *   php artisan tenants:eliminar-definitivo la-hacienda --keep-dns (no toca Hostinger)
 *   php artisan tenants:eliminar-definitivo la-hacienda --keep-data (no borra registros)
 */
class EliminarTenantDefinitivamente extends Command
{
    protected $signature = 'tenants:eliminar-definitivo
                            {slug : Slug del tenant a eliminar}
                            {--force : No pedir confirmación interactiva}
                            {--keep-dns : No tocar el DNS en Hostinger}
                            {--keep-data : No borrar los datos (solo soft-delete del tenant)}';

    protected $description = 'ELIMINA DEFINITIVAMENTE un tenant: DNS + Nginx + SSL + todos sus datos.';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');
        $dominio = "{$slug}.{$base}";

        $tenant = app(TenantManager::class)->withoutTenant(
            fn () => Tenant::where('slug', $slug)->first()
        );

        if (!$tenant) {
            $this->error("No existe tenant con slug '{$slug}'.");
            return Command::FAILURE;
        }

        $this->warn("⚠️  Vas a ELIMINAR DEFINITIVAMENTE:");
        $this->line("   • Tenant:    {$tenant->nombre} (id={$tenant->id})");
        $this->line("   • Dominio:   https://{$dominio}");
        $this->line("   • DNS Hostinger: " . ($this->option('keep-dns') ? 'NO se toca' : 'SE BORRA'));
        $this->line("   • Datos BD:  " . ($this->option('keep-data') ? 'NO se tocan' : 'SE BORRAN (pedidos, clientes, productos, ...)'));
        $this->line("   • Nginx:     SE BORRA /etc/nginx/sites-enabled/{$dominio}.conf");
        $this->line("   • SSL:       SE BORRA cert de Let's Encrypt");

        if (!$this->option('force')) {
            if (!$this->confirm("¿Confirmar eliminación TOTAL de '{$tenant->nombre}'?", false)) {
                $this->info('Cancelado.');
                return Command::SUCCESS;
            }
        }

        // ── 1. DNS en Hostinger ───────────────────────────────────────
        if (!$this->option('keep-dns')) {
            try {
                $dns = app(HostingerDnsService::class);
                if ($dns->existeSubdominio($slug)) {
                    $dns->eliminarSubdominio($slug);
                    $this->line("✓ DNS borrado en Hostinger.");
                } else {
                    $this->line("· DNS no existía en Hostinger.");
                }
            } catch (\Throwable $e) {
                $this->warn("⚠️ Error con Hostinger: " . $e->getMessage());
                $this->warn("   Continuando con el resto de la limpieza.");
            }
        }

        // ── 2. Encolar borrado de Nginx + SSL en el host ──────────────
        $pendingDir = storage_path('app/nginx-tenants');
        if (!is_dir($pendingDir)) @mkdir($pendingDir, 0775, true);

        $deletePath = $pendingDir . "/{$dominio}.conf.delete";
        File::put($deletePath, json_encode([
            'dominio' => $dominio,
            'slug'    => $slug,
            'creado_en' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $this->line("✓ Encolado para el watcher del host:");
        $this->line("   {$deletePath}");

        // ── 3. Eliminar datos del tenant ──────────────────────────────
        if (!$this->option('keep-data')) {
            DB::transaction(function () use ($tenant) {
                $tenantId = $tenant->id;

                // Tablas con tenant_id — orden importa por FKs
                $tablasConTenant = [
                    'detalles_pedido',     // FK a pedidos
                    'pedidos',
                    'detalles_promocion',
                    'promociones',
                    'productos',
                    'categorias',
                    'clientes',
                    'conversaciones',
                    'mensajes_chat',
                    'bot_alertas',
                    'felicitaciones',
                    'domiciliarios',
                    'zonas',
                    'despachos',
                    'sedes',
                    'pagos',
                    'suscripciones',
                ];

                foreach ($tablasConTenant as $tabla) {
                    if (\Schema::hasTable($tabla) && \Schema::hasColumn($tabla, 'tenant_id')) {
                        $count = DB::table($tabla)->where('tenant_id', $tenantId)->delete();
                        if ($count > 0) {
                            $this->line("   · {$tabla}: {$count} registros borrados");
                        }
                    }
                }

                // Users del tenant (NO super-admins, esos tienen tenant_id NULL)
                $usersBorrados = DB::table('users')->where('tenant_id', $tenantId)->delete();
                if ($usersBorrados > 0) {
                    $this->line("   · users: {$usersBorrados} cuentas borradas");
                }
            });

            $this->line("✓ Datos del tenant eliminados.");
        }

        // ── 4. Soft-delete del tenant ─────────────────────────────────
        $tenant->delete();
        $this->line("✓ Tenant marcado como eliminado (soft-delete).");

        $this->info('');
        $this->info("🎉 Eliminación completa de '{$tenant->nombre}'.");
        $this->info("   El watcher del host borrará Nginx + SSL en ~5 segundos.");

        return Command::SUCCESS;
    }
}
