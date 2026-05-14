<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionBot;
use App\Models\Integracion;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\Tenant;
use App\Services\IntegracionSyncService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🔄 Sincroniza los productos del ERP (SGI) a la tabla local `productos`.
 *
 * Filosofía:
 *  - El precio y disponibilidad SIGUEN viniendo en vivo del ERP cuando
 *    `fuente_productos = integracion` (modo híbrido).
 *  - Esta sincronización CREA/ACTUALIZA productos locales para que el admin
 *    pueda agregarles fotos, palabras clave, marcar como destacados, asociar
 *    con promociones, etc. SIN tocar el ERP.
 *  - Los campos GESTIONADOS desde la app (destacado, palabras_clave, imagen_url,
 *    descripcion_corta, orden, palabras_clave, foto, asociaciones con sede)
 *    NO se sobreescriben en cada sync. Solo se actualizan nombre, precio_base,
 *    unidad y categoría.
 *
 * Uso:
 *   php artisan productos:sincronizar-desde-erp                       # todos los tenants
 *   php artisan productos:sincronizar-desde-erp --tenant=1            # un tenant
 *   php artisan productos:sincronizar-desde-erp --integracion=3       # una integración
 *   php artisan productos:sincronizar-desde-erp --dry-run             # simulación
 */
class SincronizarProductosDesdeErp extends Command
{
    protected $signature = 'productos:sincronizar-desde-erp
                            {--tenant= : Solo este tenant_id}
                            {--integracion= : Solo esta integracion_id}
                            {--dry-run : Mostrar qué pasaría sin escribir nada}';

    protected $description = 'Sincroniza productos del ERP a la tabla local manteniendo conexión live para precios';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');
        $integracionId = $this->option('integracion');

        $tenants = Tenant::query()->where('activo', true);
        if ($tenantId) $tenants->where('id', $tenantId);
        $tenants = $tenants->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants activos.');
            return self::SUCCESS;
        }

        $totalCreados = 0;
        $totalActualizados = 0;
        $totalSinCambio = 0;
        $totalErrores = 0;

        foreach ($tenants as $tenant) {
            try {
                app(TenantManager::class)->set($tenant);

                $cfg = ConfiguracionBot::actual();
                $integ = null;

                if ($integracionId) {
                    $integ = Integracion::where('id', $integracionId)
                        ->where('tenant_id', $tenant->id)
                        ->first();
                } elseif ($cfg && $cfg->integracion_productos_id) {
                    $integ = Integracion::find($cfg->integracion_productos_id);
                }

                if (!$integ || !$integ->activo) {
                    $this->line("Tenant #{$tenant->id} ({$tenant->nombre}): sin integración de productos activa. Skip.");
                    continue;
                }

                $this->info("🔄 Tenant #{$tenant->id} {$tenant->nombre} — integración #{$integ->id}");

                $stats = $this->procesarTenant($tenant, $integ, $dryRun);
                $totalCreados      += $stats['creados'];
                $totalActualizados += $stats['actualizados'];
                $totalSinCambio    += $stats['sin_cambio'];
                $totalErrores      += $stats['errores'];

                $this->line("   → Creados: {$stats['creados']} · Actualizados: {$stats['actualizados']} · Sin cambio: {$stats['sin_cambio']} · Errores: {$stats['errores']}");
            } catch (\Throwable $e) {
                Log::error('❌ Sync productos ERP: error en tenant', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("   ❌ Error tenant #{$tenant->id}: " . $e->getMessage());
                $totalErrores++;
            }
        }

        $this->newLine();
        $this->info("✅ Resumen total: {$totalCreados} creados · {$totalActualizados} actualizados · {$totalSinCambio} sin cambio · {$totalErrores} errores");

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN: no se escribió nada. Quita --dry-run para ejecutar de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * Procesa un tenant: lee productos del ERP y hace UPSERT en `productos`.
     */
    private function procesarTenant(Tenant $tenant, Integracion $integ, bool $dryRun): array
    {
        $sync = app(IntegracionSyncService::class);
        $productosLive = $sync->leerProductosLive($integ);

        if ($productosLive->isEmpty()) {
            $this->warn("   ⚠️  Query del ERP devolvió 0 filas.");
            return ['creados' => 0, 'actualizados' => 0, 'sin_cambio' => 0, 'errores' => 0];
        }

        $this->line("   📦 ERP devolvió {$productosLive->count()} productos");

        // Cache de categorías para no repetir queries
        $categoriasCache = ProductoCategoria::where('tenant_id', $tenant->id)
            ->get()
            ->keyBy(fn ($c) => mb_strtolower($c->nombre));

        $stats = ['creados' => 0, 'actualizados' => 0, 'sin_cambio' => 0, 'errores' => 0];

        foreach ($productosLive as $live) {
            try {
                $codigo = trim((string) ($live->codigo ?? ''));
                $nombre = trim((string) ($live->nombre ?? ''));
                if ($codigo === '' && $nombre === '') {
                    continue;
                }

                // Limpiar UTF-8 (SGI a veces devuelve latín-1)
                $nombre      = $this->limpiarUtf8($nombre);
                $descripcion = $this->limpiarUtf8((string) ($live->descripcion ?? ''));
                $categoriaN  = $this->limpiarUtf8((string) ($live->categoria ?? ''));
                $unidad      = trim((string) ($live->unidad ?? 'unidad'));
                $precio      = (float) ($live->precio_base ?? 0);

                // Resolver/crear categoría
                $categoriaId = null;
                if ($categoriaN !== '') {
                    $key = mb_strtolower($categoriaN);
                    $cat = $categoriasCache->get($key);
                    if (!$cat) {
                        if (!$dryRun) {
                            $cat = ProductoCategoria::create([
                                'tenant_id' => $tenant->id,
                                'nombre'    => $categoriaN,
                                'slug'      => Str::slug($categoriaN),
                                'activo'    => true,
                            ]);
                            $categoriasCache->put($key, $cat);
                        }
                    }
                    $categoriaId = $cat?->id;
                }

                // Buscar producto existente (por código primero, luego nombre)
                $existente = null;
                if ($codigo !== '') {
                    $existente = Producto::where('tenant_id', $tenant->id)
                        ->where('codigo', $codigo)->first();
                }
                if (!$existente && $nombre !== '') {
                    $existente = Producto::where('tenant_id', $tenant->id)
                        ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                        ->first();
                }

                $datos = [
                    'tenant_id'           => $tenant->id,
                    'codigo'              => $codigo,
                    'nombre'              => $nombre,
                    'descripcion'         => $descripcion ?: null,
                    'unidad'              => $unidad ?: 'unidad',
                    'precio_base'         => $precio,
                    'categoria_id'        => $categoriaId,
                    'erp_integracion_id'  => $integ->id,
                    'erp_sincronizado_at' => now(),
                ];

                if ($existente) {
                    // Detectar cambios reales en campos del ERP
                    $cambios = [];
                    foreach (['nombre', 'descripcion', 'unidad', 'precio_base', 'categoria_id'] as $f) {
                        if ((string) $existente->{$f} !== (string) $datos[$f]) {
                            $cambios[$f] = ['antes' => $existente->{$f}, 'despues' => $datos[$f]];
                        }
                    }

                    if (empty($cambios)) {
                        $stats['sin_cambio']++;
                        if (!$dryRun) {
                            // Solo actualizar el timestamp del sync
                            $existente->forceFill([
                                'erp_sincronizado_at' => now(),
                                'erp_integracion_id'  => $integ->id,
                            ])->saveQuietly();
                        }
                        continue;
                    }

                    if (!$dryRun) {
                        // Solo actualizar campos del ERP — preservar fotos, palabras clave, destacado, etc.
                        $existente->update(array_intersect_key($datos, array_flip([
                            'nombre', 'descripcion', 'unidad', 'precio_base', 'categoria_id',
                            'codigo', 'erp_integracion_id', 'erp_sincronizado_at',
                        ])));
                    }
                    $stats['actualizados']++;
                } else {
                    // Crear nuevo
                    $datos['activo'] = true;
                    if (!$dryRun) {
                        Producto::create($datos);
                    }
                    $stats['creados']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Sync productos: producto falló', [
                    'tenant_id' => $tenant->id,
                    'codigo'    => $codigo ?? null,
                    'nombre'    => $nombre ?? null,
                    'error'     => $e->getMessage(),
                ]);
                $stats['errores']++;
            }
        }

        return $stats;
    }

    /**
     * Convierte string a UTF-8 válido. SGI a veces devuelve latín-1.
     */
    private function limpiarUtf8(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252') ?: $s;
        }
        // Quitar bytes de control
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        return trim($s) ?: null;
    }
}
