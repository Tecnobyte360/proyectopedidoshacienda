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

                    // 🪞 Reflejo JIT a tabla local: cada lectura live mantiene
                    // `productos` actualizada (solo campos del ERP — preserva
                    // imagen, palabras_clave, destacado, orden, etc).
                    // Corre dentro de try/catch para nunca romper el catálogo.
                    try {
                        $this->reflejarLiveEnLocal($liveRows, $integracion);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('JIT mirror live→local falló', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Lookup local por codigo (todos de un solo query)
                    $codigos = $liveRows->pluck('codigo')->filter()->unique()->values()->all();
                    $localesPorCodigo = collect();

                    if (!empty($codigos)) {
                        $localesPorCodigo = Producto::with(['categoria'])
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

                    // 2) Productos locales SIN código (virtuales — ej combos creados
                    //    desde la plataforma que no existen en SGI). Los incluimos
                    //    igual para no perderlos.
                    //
                    //    🛡️ ANTES: incluíamos TODOS los locales cuyo codigo no estaba
                    //    en el ERP. Eso reintroducía productos descontinuados o de
                    //    otras líneas (ej SUPERCOCO al filtrar SGI por StrLinea).
                    //    AHORA: solo locales sin código (productos virtuales).
                    $localesExtras = Producto::with(['categoria'])
                        ->where('activo', true)
                        ->where(function ($q) {
                            $q->whereNull('codigo')->orWhere('codigo', '');
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
        // ⚠️ NO cargar la relación 'sedes' eager — cada sede trae su polígono
        // de cobertura completo (cientos de KB de coords GPS) y revienta el cache.
        // El filtro por sede se hace via scope disponibleEnSede en el query.
        $cacheKey = "bot_catalogo_productos_t{$tenantId}_" . ($sedeId ?? 'all');
        $productos = Cache::remember($cacheKey, 120, function () use ($sedeId) {
            $query = Producto::query()
                ->with(['categoria'])
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

                // 🆕 Incluir descripcion_corta (variedad/presentación) si existe.
                // Sin esto, productos con nombre genérico ("Guayacán Rosado Reserva
                // Especial") aparecen 6 veces idénticos y el bot los confunde con
                // duplicados → solo menciona 2 al cliente.
                $variante = !empty($p->descripcion_corta)
                    ? ' (' . trim($p->descripcion_corta) . ')'
                    : '';

                $lineas[] = sprintf(
                    '  • %s%s%s — $%s/%s%s',
                    $codigo,
                    $p->nombre,
                    $variante,
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
     *
     * Combina:
     *   - Zonas legacy (tabla zonas_cobertura) — sistema viejo por barrios
     *   - Cobertura por sede (sedes.cobertura_poligono) — sistema nuevo
     *
     * Y CIERRA con una regla dura anti-alucinación: si el cliente pregunta
     * por un país/ciudad que NO esté en estas zonas, el bot DEBE decir que
     * no hay cobertura, sin importar lo que diga el prompt maestro.
     */
    public function zonasFormateadas(?int $sedeId = null): string
    {
        $zonas = $this->zonasActivas($sedeId);
        $sedesConCobertura = $this->sedesConCoberturaResumen($sedeId);

        $lineas = [];

        // Zonas legacy
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

        // Cobertura por polígono de cada sede
        foreach ($sedesConCobertura as $resumen) {
            $lineas[] = $resumen;
        }

        if (empty($lineas)) {
            return "(⚠️ No hay zonas de cobertura configuradas. Antes de prometer entregas, "
                 . "siempre llama a `validar_cobertura` con la dirección. "
                 . "Si devuelve no cubierta, NO inventes cobertura — ofrece recoger en sede.)";
        }

        $texto = implode("\n", $lineas);

        // 🔒 REGLA DURA ANTI-ALUCINACIÓN
        $texto .= "\n\n⚠️ **ALCANCE DE COBERTURA REAL — REGLA INVIOLABLE**\n";
        $texto .= "Las zonas de arriba son las ÚNICAS donde se hacen entregas a domicilio.\n";
        $texto .= "Si el cliente pregunta por un país, ciudad, departamento o región que NO está\n";
        $texto .= "explícitamente listado arriba, debes responder que actualmente NO hay cobertura\n";
        $texto .= "en ese lugar y ofrecer recoger en sede o tomar nota para futuro contacto.\n";
        $texto .= "❌ PROHIBIDO afirmar que se hacen envíos internacionales (FedEx, DHL, etc) si\n";
        $texto .= "no aparece explícitamente arriba — aunque el prompt maestro lo mencione.\n";
        $texto .= "❌ PROHIBIDO inventar cobertura para Brasil, USA, Europa, etc, si no está listada.\n";
        $texto .= "✅ Si el cliente da una dirección específica, SIEMPRE llama `validar_cobertura`\n";
        $texto .= "antes de confirmar — la herramienta valida contra el polígono real configurado.";

        return $texto;
    }

    /**
     * Resumen humano de la cobertura por sede (sedes.cobertura_poligono).
     * Usa Reverse-Geocoding del centro de cada zona para nombrar el área.
     */
    private function sedesConCoberturaResumen(?int $sedeId = null): array
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        if (!$tenantId) return [];

        $q = \App\Models\Sede::where('tenant_id', $tenantId)
            ->where('activa', true)
            ->where('cobertura_activa', true);

        if ($sedeId) $q->where('id', $sedeId);

        $sedes = $q->get();
        $resumenes = [];

        foreach ($sedes as $sede) {
            if (!$sede->tieneCobertura()) continue;

            $polys = $sede->poligonosNormalizados();
            $costo = $sede->cobertura_costo_envio > 0
                ? '$' . $this->formatNumber($sede->cobertura_costo_envio)
                : 'gratis';
            $tiempo = $sede->cobertura_tiempo_min ? " (~{$sede->cobertura_tiempo_min} min)" : '';

            // Describir cada zona por su bbox aproximado
            $descripciones = [];
            foreach ($polys as $i => $poly) {
                $lats = array_column($poly, 0);
                $lngs = array_column($poly, 1);
                if (empty($lats)) continue;

                $latMin = min($lats); $latMax = max($lats);
                $lngMin = min($lngs); $lngMax = max($lngs);
                $rangoLat = $latMax - $latMin;
                $rangoLng = $lngMax - $lngMin;

                // Heurística simple: si rango > 8°, probablemente es país; > 1°, departamento; menos, ciudad/barrio
                if ($rangoLat > 8 || $rangoLng > 8) {
                    $descripciones[] = "Colombia (toda)";
                } elseif ($rangoLat > 2 || $rangoLng > 2) {
                    $descripciones[] = "región amplia (~" . round(max($rangoLat, $rangoLng) * 111) . " km)";
                } elseif ($rangoLat > 0.3 || $rangoLng > 0.3) {
                    $descripciones[] = "área metropolitana o departamento";
                } else {
                    $descripciones[] = "ciudad/barrio (~" . round(max($rangoLat, $rangoLng) * 111, 1) . " km)";
                }
            }

            $zonasTexto = implode(' + ', array_unique($descripciones));
            // Evita "Sede Sede Medellín" si el nombre ya empieza con "Sede"
            $prefijo = stripos(trim($sede->nombre), 'sede') === 0 ? '' : 'Sede ';
            $resumenes[] = "📍 {$prefijo}{$sede->nombre}: cubre {$zonasTexto} — domicilio {$costo}{$tiempo}";
        }

        return $resumenes;
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

        // 🛡️ Si la entrada es solo una palabra de método de entrega o funcional,
        // NO matchear como producto. El catálogo a veces tiene 'DOMICILIO' o
        // similar como ítem (cargo de envío) y eso confunde.
        $palabrasNoProducto = [
            'domicilio', 'domicilios', 'domiicilio', 'dominicio', 'dominici',
            'despacho', 'despachos', 'envio', 'envío', 'envios', 'envíos',
            'recoger', 'recogerlo', 'reclamo', 'reclamar', 'entrega', 'entregas',
            'pago', 'efectivo', 'transferencia', 'tarjeta',
            'hola', 'gracias', 'si', 'no', 'dale', 'listo', 'ok',
        ];
        if (in_array($entrada, $palabrasNoProducto, true)) {
            \Log::warning('🛡️ resolverProducto rechazó palabra no-producto', [
                'entrada' => $nombreOCodigo,
            ]);
            return null;
        }

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
        $parcial = $productos->first(function ($p) use ($entrada) {
            $nombre = $this->normalizar((string) $p->nombre);
            return str_contains($nombre, $entrada) || str_contains($entrada, $nombre);
        });
        if ($parcial) return $parcial;

        // 5) FUZZY MATCH — tolera typos comunes (Huayacan → Guayacán, etc)
        // 🛡️ ENDURECIDO: además del threshold de similitud (>=85), EXIGIMOS
        // que al menos UN token significativo (>=4 chars) de la entrada
        // aparezca contenido o muy cercano (lev<=2) en el nombre del producto.
        // Esto evita matches catastróficos tipo "Pierna de cerdo" → "SUPERCOCO".
        $tokensEntrada = collect(explode(' ', $entrada))
            ->filter(fn ($t) => mb_strlen($t) >= 4)
            ->values();

        $mejor = null;
        $mejorScore = 0.0;

        foreach ($productos as $p) {
            $nombre = $this->normalizar((string) $p->nombre);
            if ($nombre === '') continue;

            // Guard de token compartido: si la entrada tiene tokens significativos,
            // al menos uno debe estar en el nombre del producto (literal o lev<=2).
            if ($tokensEntrada->isNotEmpty()) {
                $palabrasNombre = explode(' ', $nombre);
                $compartido = false;
                foreach ($tokensEntrada as $tE) {
                    if (str_contains($nombre, $tE)) { $compartido = true; break; }
                    foreach ($palabrasNombre as $pN) {
                        if (mb_strlen($pN) < 4) continue;
                        if (abs(mb_strlen($pN) - mb_strlen($tE)) > 2) continue;
                        if (levenshtein($tE, $pN) <= 2) { $compartido = true; break 2; }
                    }
                }
                if (!$compartido) continue; // descarta candidato
            }

            similar_text($entrada, $nombre, $similitud);

            $primerTokenNombre = explode(' ', $nombre)[0] ?? '';
            $primerTokenEntrada = explode(' ', $entrada)[0] ?? '';
            $sim2 = 0.0;
            if ($primerTokenNombre && $primerTokenEntrada) {
                similar_text($primerTokenEntrada, $primerTokenNombre, $sim2);
            }

            $score = max($similitud, $sim2);

            // Threshold subido de 70 → 85
            if ($score > $mejorScore && $score >= 85) {
                $mejorScore = $score;
                $mejor = $p;
            }
        }

        if ($mejor) {
            \Log::info('🎯 Producto resuelto por fuzzy match', [
                'entrada' => $entrada,
                'producto' => $mejor->nombre,
                'codigo'   => $mejor->codigo,
                'score'    => round($mejorScore, 1),
            ]);
        } else {
            \Log::info('🚫 Fuzzy match NO resolvió producto', [
                'entrada' => $entrada,
                'tokens_significativos' => $tokensEntrada->all(),
            ]);
        }

        return $mejor;
    }

    /**
     * Refleja los productos leídos en vivo del ERP a la tabla local `productos`.
     *
     * Filosofía:
     *  - Cada lectura live (cada 30s vía cache) mantiene la tabla local
     *    "espejada" SIN tocar campos gestionados por el admin (imagen,
     *    palabras_clave, destacado, orden, descripcion_corta).
     *  - Solo escribe campos del ERP: nombre, descripcion, unidad, precio_base
     *    y categoria_id.
     *  - Crea categorías que no existan.
     *  - El catálogo del bot SIGUE usando los precios live; la tabla local es
     *    para que el admin pueda enlazar promociones, fotos, etc.
     */
    private function reflejarLiveEnLocal(Collection $liveRows, \App\Models\Integracion $integracion): void
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        if (!$tenantId) return;

        // Cache de categorías local (1 query)
        $categoriasCache = \App\Models\ProductoCategoria::where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn ($c) => mb_strtolower($c->nombre));

        // Lookup local por código (1 query)
        $codigos = $liveRows->pluck('codigo')->filter()->unique()->values()->all();
        $localesPorCodigo = collect();
        if (!empty($codigos)) {
            $localesPorCodigo = \App\Models\Producto::where('tenant_id', $tenantId)
                ->whereIn('codigo', $codigos)
                ->get()
                ->keyBy(fn ($p) => (string) $p->codigo);
        }

        foreach ($liveRows as $live) {
            try {
                $codigo = trim((string) ($live->codigo ?? ''));
                $nombre = $this->limpiarUtf8(trim((string) ($live->nombre ?? '')));
                if ($codigo === '' || $nombre === '') continue;

                $descripcion = $this->limpiarUtf8((string) ($live->descripcion ?? ''));
                $categoriaN  = $this->limpiarUtf8((string) ($live->categoria ?? ''));
                $unidad      = trim((string) ($live->unidad ?? 'unidad')) ?: 'unidad';
                $precio      = (float) ($live->precio_base ?? 0);

                // Resolver/crear categoría
                $categoriaId = null;
                if ($categoriaN !== '') {
                    $key = mb_strtolower($categoriaN);
                    $cat = $categoriasCache->get($key);
                    if (!$cat) {
                        $cat = \App\Models\ProductoCategoria::create([
                            'tenant_id' => $tenantId,
                            'nombre'    => $categoriaN,
                            'slug'      => Str::slug($categoriaN),
                            'activo'    => true,
                        ]);
                        $categoriasCache->put($key, $cat);
                    }
                    $categoriaId = $cat->id;
                }

                $existente = $localesPorCodigo->get($codigo);

                $datosErp = [
                    'nombre'              => $nombre,
                    'descripcion'         => $descripcion ?: null,
                    'unidad'              => $unidad,
                    'precio_base'         => $precio,
                    'categoria_id'        => $categoriaId,
                    'erp_integracion_id'  => $integracion->id,
                    'erp_sincronizado_at' => now(),
                ];

                if ($existente) {
                    // Detectar cambios reales (evita writes innecesarios)
                    $cambio = false;
                    foreach (['nombre', 'descripcion', 'unidad', 'precio_base', 'categoria_id'] as $f) {
                        if ((string) $existente->{$f} !== (string) $datosErp[$f]) {
                            $cambio = true;
                            break;
                        }
                    }
                    if ($cambio) {
                        $existente->update($datosErp);
                    } else {
                        // Solo refrescar timestamp sin disparar observers
                        $existente->forceFill([
                            'erp_sincronizado_at' => now(),
                            'erp_integracion_id'  => $integracion->id,
                        ])->saveQuietly();
                    }
                } else {
                    \App\Models\Producto::create(array_merge($datosErp, [
                        'tenant_id' => $tenantId,
                        'codigo'    => $codigo,
                        'activo'    => true,
                    ]));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('JIT mirror: fila falló', [
                    'codigo' => $codigo ?? null,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sanitiza UTF-8 — SGI a veces devuelve Latin-1 con bytes de control.
     */
    private function limpiarUtf8(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1, Windows-1252') ?: $s;
        }
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        return trim($s) ?: null;
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
