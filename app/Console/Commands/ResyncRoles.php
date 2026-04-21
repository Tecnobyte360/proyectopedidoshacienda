<?php

namespace App\Console\Commands;

use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-sincroniza los permisos del rol `admin` para QUITARLE los permisos
 * de plataforma (tenants.gestionar, planes.gestionar, pagos.gestionar,
 * suscripciones.gestionar, roles.gestionar) que solo el super-admin debe tener.
 *
 * Útil después de detectar que el rol admin estaba sobre-permisado.
 *
 * Uso:
 *   php artisan tenants:resync-roles
 *   php artisan tenants:resync-roles --dry-run    (solo muestra qué haría)
 */
class ResyncRoles extends Command
{
    protected $signature = 'tenants:resync-roles {--dry-run : Solo muestra los cambios sin aplicarlos}';
    protected $description = 'Re-sincroniza permisos: quita permisos de plataforma al rol admin (los deja exclusivos del super-admin).';

    private array $permisosPlataforma = [
        'tenants.gestionar',
        'planes.gestionar',
        'suscripciones.gestionar',
        'pagos.gestionar',
        'roles.gestionar',
    ];

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Revisando permisos del rol "admin"...');

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if (!$admin) {
            $this->error('No existe el rol "admin". Corre RolesPermisosSeeder primero.');
            return Command::FAILURE;
        }

        $permisosActuales = $admin->permissions->pluck('name')->all();
        $aQuitar = array_intersect($permisosActuales, $this->permisosPlataforma);

        if (empty($aQuitar)) {
            $this->info('✅ El rol admin ya está limpio. No tiene permisos de plataforma.');
        } else {
            $this->warn('⚠️  El rol admin tiene permisos de plataforma que NO debería tener:');
            foreach ($aQuitar as $p) {
                $this->line("   - {$p}");
            }
            if (!$dryRun) {
                foreach ($aQuitar as $p) {
                    $admin->revokePermissionTo($p);
                }
                $this->info('✓ Permisos revocados del rol admin.');
            } else {
                $this->warn('(dry-run: no se aplicaron cambios)');
            }
        }

        // Asegurar que super-admin SÍ los tiene
        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $faltantes = [];
            foreach ($this->permisosPlataforma as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
                if (!$superAdmin->hasPermissionTo($p)) {
                    $faltantes[] = $p;
                    if (!$dryRun) {
                        $superAdmin->givePermissionTo($p);
                    }
                }
            }
            if (!empty($faltantes)) {
                $this->info('🛡️  Permisos restaurados al super-admin:');
                foreach ($faltantes as $p) {
                    $this->line("   + {$p}");
                }
            } else {
                $this->info('✅ El super-admin ya tiene todos los permisos de plataforma.');
            }
        }

        // Reset cache para que surta efecto inmediato
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('');
        $this->info('🎉 Listo. Ahora ningún admin de tenant puede acceder a /admin/tenants, /admin/planes, etc.');
        return Command::SUCCESS;
    }
}
