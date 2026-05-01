<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\Promocion;
use App\Models\ZonaCobertura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Construye el contexto del bot (catálogo, promos, zonas) y resuelve productos
 * mencionados por el cliente contra el catálogo real.
 */
class BotCatalogoService
{
    /**
     * Catálogo activo cacheado 2 minutos.
     */
    public function productosActivos(?int $sedeId = null): Collection
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';

        $config = \App\Models\ConfiguracionBot::actual();

        // ── MODO HÍBRIDO LIVE: precio/disponibilidad del ERP en tiempo real,
        //    enriquecido con datos del modelo local (cortes, fotos, palabras
        //    clave, destacados, sedes) cuando hay match por codigo.
        if ($config && $config->fuente_productos === \App\Models\ConfiguracionBot::FUENTE_INTEGRACION
            && $config->integracion_productos_id) {

            $cacheKey = "bot_catalogo_live_t{$tenantId}_i{$config->integracion_productos_id}";
            // Cache 30s para no martillar el ERP en una misma conversacion
            return Cache::remember($cacheKey, 30, function () use ($config) {
                try {
                    $integracion = \App\Models\Integracion::find($config->integracion_productos_id);
                    if (!$integracion || !$integracion->activo) return collect();

                    $liveRows = app(\App\Services\IntegracionSyncService::class)
                        ->leerProductosLive($integracion);

                    if ($liveRows->isEmpty()) return collect();

                    // Lookup local por codigo (todos de un solo query)
                    $codigos = $liveRows->pluck('codigo')->filter()->unique()->values()->all();
                    $localesPorCodigo = collect();

                    if (!empty($codigos)) {
                        $localesPorCodigo = Producto::with(['categoria', 'sedes'])
                            ->whereIn('codigo', $codigos)
                            ->get()
                            ->keyBy(fn ($p) => (string) $p->codigo);
                    }

                    // 1) Por cada fila del ERP: si hay match local → usar Producto
                    //    Eloquent con precio sobreescrito; si no → stdClass virtual.
                    $resultadoErp = $liveRows->map(function ($row) use ($localesPorCodigo) {
                        $codigo = (string) ($row->codigo ?? '');
                        $local  = $codigo !== '' ? $localesPorCodigo->get($codigo) : null;

                        if ($local) {
                            $local->precio_base = (float) ($row->precio_base ?? $local->precio_base);
                            if (!empty($row->unidad)) {
                                $local->unidad = $row->unidad;
                            }
                            $local->setAttribute('_fuente', 'erp+local');
                            return $local;
                        }

                        $row->_fuente = 'solo_erp';
                        return $row;
                    });

                    // 2) Productos locales que NO estan en el ERP — los incluimos
                    //    igual para que el bot no los pierda ("PIERNA A LA PARRILLA"
                    //    en local, "PIERNA" en ERP, etc.).
                    $codigosErp = $liveRows->pluck('codigo')
                        ->filter(fn ($c) => $c !== null && $c !== '')
                        ->map(fn ($c) => (string) $c)
                        ->unique()
                        ->values()
                        ->all();

                    $localesExtras = Producto::with(['categoria', 'sedes'])
                        ->where('activo', true)
                        ->where(function ($q) use ($codigosErp) {
                            $q->whereNull('codigo')->orWhere('codigo', '');
                            if (!empty($codigosErp)) {
                                $q->orWhereNotIn('codigo', $codigosErp);
                            }
                        })
                        ->orderBy('orden')
                        ->orderBy('nombre')
                        ->get()
                        ->map(function ($p) {
                            $p->setAttribute('_fuente', 'solo_local');
                            return $p;
                        });

                    return $this->filtrarCatalogo(
                        $resultadoErp->concat($localesExtras)->values(),
                        $config
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Live read productos fallo, fallback a tabla local', [
                        'tenant' => app(\App\Services\TenantManager::class)->id(),
                        'error'  => $e->getMessage(),
                    ]);
                    // Fallback: si la BD externa esta caida, usar la tabla local
                    return Producto::query()->with('categoria')->where('activo', true)->get();
                }
            });
        }

        // ── MODO TABLA: lectura normal del modelo local ──
        $cacheKey = "bot_catalogo_productos_t{$tenantId}_" . ($sedeId ?? 'all');
        $productos = Cache::remember($cacheKey, 120, function () use ($sedeId) {
            $query = Producto::query()
                ->with(['categoria', 'sedes'])
                ->where('activo', true);

            if ($sedeId) {
                $query->disponibleEnSede($sedeId);
            }

            return $query->orderBy('orden')->orderBy('nombre')->get();
        });

        return $this->filtrarCatalogo($productos, $config);
    }

    /**
     * Aplica filtros del bot al catalogo:
     *  - Categorias excluidas
     *  - Productos sin precio (si esta activo)
     */
    private function filtrarCatalogo(Collection $productos, $config): Collection
    {
        if (!$config) return $productos;

        $excluidas = collect($config->categorias_excluidas_bot ?? [])
            ->filter()
            ->map(fn ($c) => mb_strtolower(trim((string) $c)))
            ->unique()
            ->values()
            ->all();

        $excluirSinPrecio = (bool) ($config->excluir_productos_sin_precio ?? true);

        if (empty($excluidas) && !$excluirSinPrecio) return $productos;

        return $productos->reject(function ($p) use ($excluidas, $excluirSinPrecio) {
            // Categoria
            if (!empty($excluidas)) {
                $cat = is_object($p->categoria ?? null)
                    ? ($p->categoria->nombre ?? '')
                    : ($p->categoria ?? '');
                if (in_array(mb_strtolower(trim((string) $cat)), $excluidas, true)) {
                    return true;
                }
            }
            // Sin precio
            if ($excluirSinPrecio) {
                $precio = method_exists($p, 'precioParaSede')
                    ? $p->precioParaSede(null)
                    : (float) ($p->precio_base ?? 0);
                if ($precio <= 0) return true;
            }
            return false;
        })->values();
    }

    /**
     * Si la config del bot tiene fuente=integracion y la ventana de auto-sync
     * vencio, ejecuta la sincronizacion ahi mismo. Tope de un sync por
     * tenant cada N minutos (Cache::lock evita carreras).
     */
    private function autoSyncSiCorresponde(string|int $tenantId): void
    {
        try {
            $config = \App\Models\ConfiguracionBot::actual();
            if (!$config || $config->fuente_productos !== \App\Models\ConfiguracionBot::FUENTE_INTEGRACION) {
                return;
            }
            if (!$config->integracion_productos_id) return;

            $minutos = (int) ($config->auto_sync_productos_min ?: 15);
            $ultimo  = $config->ultimo_sync_productos_at;

            if ($ultimo && $ultimo->copy()->addMinutes($minutos)->isFuture()) {
                return; // todavia esta fresco
            }

            $lock = Cache::lock("bot_auto_sync_productos_t{$tenantId}", 300);
            if (!$lock->get()) return; // otro proceso ya esta sincronizando

            try {
                $integracion = \App\Models\Integracion::find($config->integracion_productos_id);
                if (!$integracion || !$integracion->activo) return;

                app(\App\Services\IntegracionSyncService::class)->sincronizar($integracion);

                $config->update(['ultimo_sync_productos_at' => now()]);
                $this->limpiarCache(); // invalidar el cache de catalogo
            } finally {
                optional($lock)->release();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Auto-sync productos del bot fallo', [
                'tenant' => $tenantId, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sincronizacion manual (boton en la config del bot).
     */
    public function sincronizarAhora(): array
    {
        $config = \App\Models\ConfiguracionBot::actual();
        if ($config->fuente_productos !== \App\Models\ConfiguracionBot::FUENTE_INTEGRACION) {
            return ['ok' => false, 'mensaje' => 'La fuente del bot no es "integracion".'];
        }
        if (!$config->integracion_productos_id) {
            return ['ok' => false, 'mensaje' => 'No hay integracion seleccionada.'];
        }

        $integracion = \App\Models\Integracion::findOrFail($config->integracion_productos_id);
        $r = app(\App\Services\IntegracionSyncService::class)->sincronizar($integracion);

        $config->update(['ultimo_sync_productos_at' => now()]);
        $this->limpiarCache();

        return $r;
    }

    /**
     * Promociones vigentes cacheadas 2 minutos.
     */
    public function promocionesVigentes(?int $sedeId = null): Collection
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        return Cache::remember("bot_promociones_vigentes_t{$tenantId}_" . ($sedeId ?? 'all'), 120, function () use ($sedeId) {
            return Promocion::vigentes()
                ->with(['productos', 'sedes'])
                ->when($sedeId, fn ($q) => $q->where(function ($qq) use ($sedeId) {
                    $qq->where('aplica_todas_sedes', true)
                       ->orWhereHas('sedes', fn ($qqq) => $qqq->where('sede_id', $sedeId));
                }))
                ->orderBy('orden')
                ->get();
        });
    }

    /**
     * Zonas activas con polígono o barrios.
     */
    public function zonasActivas(?int $sedeId = null): Collection
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $config = \App\Models\ConfiguracionBot::actual();
        $zonasFiltro = collect($config->bot_zonas_ids ?? [])->filter()->map(fn ($v) => (int) $v)->values()->all();
        $filtroKey = empty($zonasFiltro) ? 'todas' : md5(implode(',', $zonasFiltro));

        return Cache::remember("bot_zonas_activas_t{$tenantId}_" . ($sedeId ?? 'all') . "_{$filtroKey}", 120, function () use ($sedeId, $zonasFiltro) {
            return ZonaCobertura::with('barrios')
                ->where('activa', true)
                ->when(!empty($zonasFiltro), fn ($q) => $q->whereIn('id', $zonasFiltro))
                ->when($sedeId, fn ($q) => $q->where(function ($qq) use ($sedeId) {
                    $qq->where('sede_id', $sedeId)->orWhereNull('sede_id');
                }))
                ->orderBy('orden')
                ->get();
        });
    }

    /**
     * Genera texto del catálogo para inyectar en el system prompt.
     */
    public function catalogoFormateado(?int $sedeId = null): string
    {
        $productos = $this->productosActivos($sedeId);

        if ($productos->isEmpty()) {
            return "⚠️ CATÁLOGO VACÍO — el local aún no ha cargado productos disponibles.\n"
                 . "Tu única respuesta válida es: \"En este momento no tengo productos cargados,\n"
                 . "te paso con el equipo para confirmarte qué hay disponible 🙏\".\n"
                 . "NO inventes productos. NO ofrezcas categorías genéricas. NO confirmes pedidos.";
        }

        $lineas = [];
        $porCategoria = $productos->groupBy(function ($p) {
            // Producto Eloquent: $p->categoria?->nombre. Live (stdClass): $p->categoria (string).
            if (is_object($p->categoria ?? null)) return $p->categoria->nombre ?? 'Otros';
            return $p->categoria ?: 'Otros';
        });

        // Mapa de iconos por nombre de categoria (para modo live, donde no hay relacion)
        $emojisPorCategoria = \App\Models\ProductoCategoria::pluck('icono_emoji', 'nombre')->toArray();

        foreach ($porCategoria as $categoria => $grupo) {
            $first = $grupo->first();
            $emoji = is_object($first->categoria ?? null)
                ? ($first->categoria->icono_emoji ?? '📦')
                : ($emojisPorCategoria[$categoria] ?? '📦');

            $lineas[] = "";
            $lineas[] = "{$emoji} {$categoria}";

            foreach ($grupo as $p) {
                // Precio: si es Producto Eloquent usa precioParaSede, si es live usa precio_base
                $precio = method_exists($p, 'precioParaSede')
                    ? $p->precioParaSede($sedeId)
                    : (float) ($p->precio_base ?? 0);

                $codigo = !empty($p->codigo) ? "[{$p->codigo}] " : '';
                $destacado = !empty($p->destacado) ? ' ⭐' : '';

                $lineas[] = sprintf(
                    '  • %s%s — $%s/%s%s',
                    $codigo,
                    $p->nombre,
                    number_format($precio, 0, ',', '.'),
                    $p->unidad ?? 'unidad',
                    $destacado
                );

                // Cortes solo si es Eloquent con id (live no tiene)
                if ($p instanceof Producto && !empty($p->id)) {
                    $cortes = $p->cortes()->where('activo', true)->pluck('nombre');
                    if ($cortes->isNotEmpty()) {
                        $lineas[] = '      ✂️ Cortes: ' . $cortes->implode(', ');
                    }
                }
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Genera texto de promociones vigentes para inyectar en el prompt.
     */
    public function promocionesFormateadas(?int $sedeId = null): string
    {
        $promos = $this->promocionesVigentes($sedeId);

        if ($promos->isEmpty()) {
            return "(No hay promociones vigentes)";
        }

        $lineas = [];
        foreach ($promos as $p) {
            $linea = "🎁 {$p->nombre}: {$p->descripcionCorta()}";

            if ($p->codigo_cupon) {
                $linea .= " (cupón: {$p->codigo_cupon})";
            }

            if (!$p->aplica_todos_productos && $p->productos->count() > 0) {
                $nombres = $p->productos->take(3)->pluck('nombre')->join(', ');
                $linea .= " — aplica a: {$nombres}";
            }

            if ($p->fecha_fin) {
                $linea .= " (hasta {$p->fecha_fin->format('d/m/Y')})";
            }

            $lineas[] = $linea;
        }

        return implode("\n", $lineas);
    }

    /**
     * Genera texto de zonas de cobertura para inyectar en el prompt.
     */
    public function zonasFormateadas(?int $sedeId = null): string
    {
        $zonas = $this->zonasActivas($sedeId);

        if ($zonas->isEmpty()) {
            return "(No hay zonas de cobertura configuradas — pregunta el barrio y valida con el equipo)";
        }

        $lineas = [];
        foreach ($zonas as $z) {
            $linea = "📍 {$z->nombre}";

            if ($z->costo_envio > 0) {
                $linea .= " — domicilio \${$this->formatNumber($z->costo_envio)}";
            } else {
                $linea .= " — domicilio gratis";
            }

            if ($z->tiempo_estimado_min) {
                $linea .= " (~{$z->tiempo_estimado_min} min)";
            }

            if ($z->barrios->count() > 0) {
                $barrios = $z->barrios->take(8)->pluck('nombre')->join(', ');
                $linea .= "\n   Barrios: {$barrios}";
                if ($z->barrios->count() > 8) {
                    $linea .= " y " . ($z->barrios->count() - 8) . " más";
                }
            }

            $lineas[] = $linea;
        }

        return implode("\n", $lineas);
    }

    /**
     * Resuelve un producto del catálogo a partir del nombre que dijo la IA.
     * Estrategias en orden:
     *  1) Match exacto por código (SKU)
     *  2) Match exacto por nombre normalizado
     *  3) Match por palabras_clave (contiene)
     *  4) Match parcial por nombre (LIKE)
     */
    public function resolverProducto(string $nombreOCodigo, ?int $sedeId = null)
    {
        $entrada = $this->normalizar($nombreOCodigo);
        if ($entrada === '') return null;

        $productos = $this->productosActivos($sedeId);

        // 1) Match por código
        $porCodigo = $productos->first(function ($p) use ($nombreOCodigo) {
            return !empty($p->codigo) && strcasecmp(trim($p->codigo), trim($nombreOCodigo)) === 0;
        });
        if ($porCodigo) return $porCodigo;

        // 2) Match exacto por nombre normalizado
        $porNombreExacto = $productos->first(function ($p) use ($entrada) {
            return $this->normalizar((string) $p->nombre) === $entrada;
        });
        if ($porNombreExacto) return $porNombreExacto;

        // 3) Palabras clave + nombre + descripcion (en live no hay palabras_clave)
        $tokens = collect(explode(' ', $entrada))->filter()->values();

        $candidatos = $productos->filter(function ($p) use ($tokens) {
            $bag = collect($p->palabras_clave ?? [])
                ->push($p->nombre ?? '')
                ->push($p->descripcion_corta ?? $p->descripcion ?? '')
                ->map(fn ($t) => $this->normalizar((string) $t))
                ->join(' ');

            return $tokens->every(fn ($t) => str_contains($bag, $t));
        });

        if ($candidatos->isNotEmpty()) {
            return $candidatos->first();
        }

        // 4) Coincidencia parcial
        return $productos->first(function ($p) use ($entrada) {
            $nombre = $this->normalizar((string) $p->nombre);
            return str_contains($nombre, $entrada) || str_contains($entrada, $nombre);
        });
    }

    /**
     * Limpia caches del catálogo (llamar cuando cambia un producto/promo/zona).
     */
    public function limpiarCache(): void
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $sufijos  = ['all'];
        foreach (\App\Models\Sede::pluck('id') as $id) {
            $sufijos[] = (string) $id;
        }
        foreach ($sufijos as $sufijo) {
            Cache::forget("bot_catalogo_productos_t{$tenantId}_{$sufijo}");
            Cache::forget("bot_promociones_vigentes_t{$tenantId}_{$sufijo}");
            Cache::forget("bot_zonas_activas_t{$tenantId}_{$sufijo}");
        }
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = Str::ascii($texto);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    private function formatNumber($n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }
}
