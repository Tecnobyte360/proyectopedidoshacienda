<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Setup inicial multi-tenant:
 *   1. Crea el rol "super-admin" (TecnoByte360, dueño de la plataforma).
 *   2. Crea el primer tenant: Alimentos La Hacienda.
 *   3. Migra TODOS los datos huérfanos (sin tenant_id) a ese primer tenant.
 *   4. Crea un usuario super-admin si no existe.
 */
class MultiTenantSetupSeeder extends Seeder
{
    /** Tablas a migrar (asignar tenant_id = 1 si está null) */
    private array $tablasMultiTenant = [
        'pedidos',
        'clientes',
        'productos',
        'categorias',
        'promociones',
        'zonas_cobertura',
        'sedes',
        'domiciliarios',
        'ans_pedidos',
        'bot_alertas',
        'felicitaciones_cumpleanos',
        'beneficios_clientes',
        'configuraciones_bot',
        'conversaciones_whatsapp',
    ];

    public function run(): void
    {
        // ── 1. ROLES ────────────────────────────────────────────────────
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());   // tiene TODO + manejo de tenants

        // Permiso adicional solo para super-admin (gestionar tenants)
        $permTenants = Permission::firstOrCreate(['name' => 'tenants.gestionar', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($permTenants);

        $this->command->info('✓ Rol super-admin creado/actualizado.');

        // ── 2. TENANT INICIAL: Alimentos La Hacienda ────────────────────
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'la-hacienda'],
            [
                'nombre'             => 'Alimentos La Hacienda',
                'plan'               => Tenant::PLAN_EMPRESA,
                'activo'             => true,
                'contacto_nombre'    => 'Stiven Madrid',
                'contacto_email'     => 'admin@hacienda.com',
                'color_primario'     => '#d68643',
                'color_secundario'   => '#a85f24',
            ]
        );

        $this->command->info("✓ Tenant inicial: {$tenant->nombre} (id={$tenant->id})");

        // ── 3. MIGRAR DATOS HUÉRFANOS ───────────────────────────────────
        // Importante: bypassear el global scope porque las queries normales
        // ya estarían filtrando por tenant_id (que es null por ahora).
        app(\App\Services\TenantManager::class)->withoutTenant(function () use ($tenant) {
            foreach ($this->tablasMultiTenant as $tabla) {
                if (!\Schema::hasTable($tabla) || !\Schema::hasColumn($tabla, 'tenant_id')) {
                    continue;
                }

                $afectados = DB::table($tabla)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenant->id]);

                if ($afectados > 0) {
                    $this->command->line("  → {$tabla}: {$afectados} registro(s) migrados al tenant.");
                }
            }
        });

        // ── 4. USUARIOS EXISTENTES → al tenant 1 (excepto super-admins) ──
        $usuariosMigrados = DB::table('users')
            ->whereNull('tenant_id')
            ->update(['tenant_id' => $tenant->id]);

        if ($usuariosMigrados > 0) {
            $this->command->line("  → users: {$usuariosMigrados} usuario(s) asignados al tenant.");
        }

        // ── 5. CREAR SUPER-ADMIN si no hay ──────────────────────────────
        $haySuperAdmin = User::role('super-admin')->whereNull('tenant_id')->exists();

        if (!$haySuperAdmin) {
            $sa = User::create([
                'name'      => 'TecnoByte Super Admin',
                'email'     => 'super@tecnobyte360.com',
                'password'  => Hash::make('superadmin123'),
                'tenant_id' => null,   // ← clave: super-admin no pertenece a ningún tenant
                'activo'    => true,
            ]);
            $sa->assignRole('super-admin');

            $this->command->info('');
            $this->command->info('🌟 SUPER-ADMIN creado:');
            $this->command->info('   Email: super@tecnobyte360.com');
            $this->command->info('   Pass:  superadmin123');
            $this->command->warn('   ⚠️  CAMBIA ESTA CONTRASEÑA EN PRODUCCIÓN');
        } else {
            $this->command->info('✓ Ya existe al menos un super-admin.');
        }

        $this->command->info('');
        $this->command->info('🎉 Multi-tenant configurado.');
    }
}
