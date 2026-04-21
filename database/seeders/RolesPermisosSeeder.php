<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    /**
     * Catálogo de permisos por módulo.
     * Cada string es un permission name único: "modulo.accion"
     */
    public const PERMISOS = [
        'pedidos' => [
            'pedidos.ver',
            'pedidos.editar',
            'pedidos.despachar',
            'pedidos.cancelar',
        ],
        'productos' => [
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.eliminar',
        ],
        'categorias' => [
            'categorias.gestionar',
        ],
        'promociones' => [
            'promociones.gestionar',
        ],
        'clientes' => [
            'clientes.ver',
            'clientes.editar',
        ],
        'conversaciones' => [
            'conversaciones.ver',
        ],
        'chat' => [
            'chat.usar',
        ],
        'domiciliarios' => [
            'domiciliarios.gestionar',
        ],
        'zonas' => [
            'zonas.gestionar',
        ],
        'despachos' => [
            'despachos.gestionar',
        ],
        'reportes' => [
            'reportes.ver',
        ],
        'ans' => [
            'ans.gestionar',
        ],
        'sedes' => [
            'sedes.gestionar',
        ],
        'configuracion_bot' => [
            'bot.configurar',
        ],
        'alertas' => [
            'alertas.ver',
            'alertas.gestionar',
        ],
        'felicitaciones' => [
            'felicitaciones.ver',
        ],
        'usuarios' => [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
        ],
        'roles' => [
            'roles.gestionar',
        ],
        'billing' => [
            'planes.gestionar',
            'suscripciones.gestionar',
            'pagos.gestionar',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Crear permisos
        foreach (self::PERMISOS as $modulo => $permisos) {
            foreach ($permisos as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            }
        }

        // 🔒 PERMISOS DE PLATAFORMA — exclusivos del super-admin (dueño TecnoByte360).
        // Un admin de tenant (cliente) NUNCA debe tenerlos, porque le darían
        // poder sobre OTROS tenants (ver/editar planes, pagos, etc.).
        $permisosPlataforma = [
            'tenants.gestionar',
            'planes.gestionar',
            'suscripciones.gestionar',
            'pagos.gestionar',
            'roles.gestionar',   // editar matriz de permisos = afectaría a TODOS los tenants
        ];

        // Permisos de operación = todos los catalogados aquí MENOS los de plataforma
        $permisosOperacion = collect(self::PERMISOS)
            ->flatten()
            ->reject(fn ($p) => in_array($p, $permisosPlataforma, true))
            ->values()
            ->all();

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permisosOperacion);

        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions([
            'pedidos.ver', 'pedidos.editar', 'pedidos.despachar', 'pedidos.cancelar',
            'productos.ver', 'productos.crear', 'productos.editar',
            'categorias.gestionar', 'promociones.gestionar',
            'clientes.ver', 'clientes.editar',
            'conversaciones.ver', 'chat.usar',
            'domiciliarios.gestionar', 'zonas.gestionar', 'despachos.gestionar',
            'reportes.ver', 'ans.gestionar',
            'felicitaciones.ver', 'alertas.ver',
        ]);

        $operador = Role::firstOrCreate(['name' => 'operador', 'guard_name' => 'web']);
        $operador->syncPermissions([
            'pedidos.ver', 'pedidos.editar', 'pedidos.despachar',
            'clientes.ver',
            'chat.usar', 'conversaciones.ver',
            'despachos.gestionar',
        ]);

        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions([
            'pedidos.ver', 'pedidos.editar',
            'clientes.ver',
        ]);

        // Crear usuario admin por defecto si no hay ninguno
        if (User::count() === 0) {
            $u = User::create([
                'name'     => 'Administrador',
                'email'    => 'admin@hacienda.com',
                'password' => Hash::make('admin123'),
                'activo'   => true,
            ]);
            $u->assignRole('admin');

            $this->command->info('✓ Admin creado: admin@hacienda.com / admin123');
        } else {
            // Si ya hay usuarios pero ninguno tiene rol, asignar admin al primero
            $primero = User::first();
            if ($primero && $primero->roles->isEmpty()) {
                $primero->assignRole('admin');
                $this->command->info("✓ Asignado rol 'admin' al usuario existente: {$primero->email}");
            }
        }
    }
}
