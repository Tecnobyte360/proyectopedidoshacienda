<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 🧬 Clona los roles del SISTEMA (tenant_id=null) dentro de cada tenant para
 * que tengan sus propios roles independientes.
 *
 * También REASIGNA los usuarios que tenían el rol global → al nuevo rol del
 * tenant, manteniendo todos sus permisos.
 *
 * Uso:
 *   php artisan tenants:bootstrap-roles                # todos los tenants
 *   php artisan tenants:bootstrap-roles --tenant=la-hacienda
 *   php artisan tenants:bootstrap-roles --dry-run      # solo muestra qué haría
 */
class TenantsBootstrapRoles extends Command
{
    protected $signature = 'tenants:bootstrap-roles
        {--tenant= : Slug de un tenant específico (opcional)}
        {--dry-run : No hace cambios, solo muestra el plan}
        {--keep-globals : NO elimina los roles globales después (default: los elimina si quedan sin usuarios)}';

    protected $description = 'Clona roles del sistema a cada tenant + reasigna usuarios. Aislamiento total por tenant.';

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry-run');
        $keepGlobals= (bool) $this->option('keep-globals');
        $tenantSlug = $this->option('tenant');

        // Roles globales que NO deben clonarse (son del dueño de la plataforma)
        $rolesNoClonables = ['super-admin'];

        // Cargar tenants objetivo
        $tenants = $tenantSlug
            ? Tenant::where('slug', $tenantSlug)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No se encontró ningún tenant.');
            return 1;
        }

        // Cargar roles globales a clonar
        $rolesGlobales = Role::whereNull('tenant_id')
            ->whereNotIn('name', $rolesNoClonables)
            ->with('permissions')
            ->get();

        $this->info("📋 Roles globales a procesar: " . $rolesGlobales->pluck('name')->implode(', '));
        $this->info("🏢 Tenants a procesar: " . $tenants->pluck('slug')->implode(', '));
        $this->newLine();

        $resumenTotal = ['creados' => 0, 'omitidos' => 0, 'usuarios_reasignados' => 0, 'globales_eliminados' => 0];

        foreach ($tenants as $tenant) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🏢 Tenant: {$tenant->nombre} (slug: {$tenant->slug}, id: {$tenant->id})");

            foreach ($rolesGlobales as $rolGlobal) {
                // ¿Ya existe un rol con ese nombre en este tenant?
                $rolTenant = Role::where('name', $rolGlobal->name)
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if ($rolTenant) {
                    $this->line("  ⏭️  '{$rolGlobal->name}' ya existe en el tenant — omitido");
                    $resumenTotal['omitidos']++;
                } else {
                    if (!$dry) {
                        // Bypass Spatie's name-unique-per-guard check inserting directo.
                        // Spatie no contempla tenant_id en su validación de unicidad.
                        $rolTenant = new Role();
                        $rolTenant->forceFill([
                            'name'       => $rolGlobal->name,
                            'guard_name' => 'web',
                            'tenant_id'  => $tenant->id,
                        ])->saveQuietly();
                        $rolTenant->syncPermissions($rolGlobal->permissions->pluck('name')->all());
                    }
                    $this->line("  ✅ Clonado '{$rolGlobal->name}' con " . $rolGlobal->permissions->count() . " permisos");
                    $resumenTotal['creados']++;
                }

                // Reasignar usuarios del tenant que tenían el rol global → rol del tenant
                $usuarios = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn($q) => $q->where('roles.id', $rolGlobal->id))
                    ->get();

                foreach ($usuarios as $u) {
                    if (!$dry && $rolTenant) {
                        $u->removeRole($rolGlobal);
                        $u->assignRole($rolTenant);
                    }
                    $this->line("    👤 Reasignado: {$u->email}");
                    $resumenTotal['usuarios_reasignados']++;
                }
            }
            $this->newLine();
        }

        // Tras procesar todos los tenants, intentar eliminar roles globales
        // que quedaron sin usuarios (excepto super-admin/admin que son críticos)
        if (!$keepGlobals && !$tenantSlug) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🧹 Limpieza: eliminando roles globales sin usuarios...");

            $rolesProtegidos = ['super-admin', 'admin']; // admin lo mantenemos por compatibilidad
            foreach ($rolesGlobales as $rolGlobal) {
                if (in_array($rolGlobal->name, $rolesProtegidos, true)) {
                    $this->line("  🛡️  '{$rolGlobal->name}' es protegido — se mantiene");
                    continue;
                }

                $rolGlobal->refresh();
                $countUsers = $rolGlobal->users()->count();
                if ($countUsers === 0) {
                    if (!$dry) {
                        $rolGlobal->delete();
                    }
                    $this->line("  🗑️  Eliminado '{$rolGlobal->name}' (sin usuarios)");
                    $resumenTotal['globales_eliminados']++;
                } else {
                    $this->line("  ⏭️  '{$rolGlobal->name}' aún tiene {$countUsers} usuario(s) — conservado");
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✅ RESUMEN:");
        $this->info("   Roles creados:        {$resumenTotal['creados']}");
        $this->info("   Roles omitidos:       {$resumenTotal['omitidos']}");
        $this->info("   Usuarios reasignados: {$resumenTotal['usuarios_reasignados']}");
        $this->info("   Globales eliminados:  {$resumenTotal['globales_eliminados']}");

        if ($dry) {
            $this->warn('⚠️  Modo DRY-RUN: no se hicieron cambios reales.');
        }

        return 0;
    }
}
