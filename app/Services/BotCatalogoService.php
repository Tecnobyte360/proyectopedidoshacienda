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
        $cacheKey = "bot_catalogo_productos_" . ($sedeId ?? 'all');

        return Cache::remember($cacheKey, 120, function () use ($sedeId) {
            $query = Producto::query()
                ->with(['categoria', 'sedes'])
                ->where('activo', true);

            if ($sedeId) {
                $query->disponibleEnSede($sedeId);
            }

            return $query->orderBy('orden')->orderBy('nombre')->get();
        });
    }

    /**
     * Promociones vigentes cacheadas 2 minutos.
     */
    public function promocionesVigentes(?int $sedeId = null): Collection
    {
        return Cache::remember("bot_promociones_vigentes_" . ($sedeId ?? 'all'), 120, function () use ($sedeId) {
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
        return Cache::remember("bot_zonas_activas_" . ($sedeId ?? 'all'), 120, function () use ($sedeId) {
            return ZonaCobertura::with('barrios')
                ->where('activa', true)
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
            return "(No hay productos activos en el catálogo)";
        }

        $lineas = [];
        $porCategoria = $productos->groupBy(fn ($p) => $p->categoria?->nombre ?? 'Otros');

        foreach ($porCategoria as $categoria => $grupo) {
            $emoji = $grupo->first()->categoria?->icono_emoji ?? '📦';
            $lineas[] = "";
            $lineas[] = "{$emoji} {$categoria}";

            foreach ($grupo as $p) {
                $precio = $p->precioParaSede($sedeId);
                $codigo = $p->codigo ? "[{$p->codigo}] " : '';
                $destacado = $p->destacado ? ' ⭐' : '';

                $lineas[] = sprintf(
                    '  • %s%s — $%s/%s%s',
                    $codigo,
                    $p->nombre,
                    number_format($precio, 0, ',', '.'),
                    $p->unidad,
                    $destacado
                );
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
    public function resolverProducto(string $nombreOCodigo, ?int $sedeId = null): ?Producto
    {
        $entrada = $this->normalizar($nombreOCodigo);
        if ($entrada === '') return null;

        $productos = $this->productosActivos($sedeId);

        // 1) Match por código
        $porCodigo = $productos->first(function ($p) use ($nombreOCodigo) {
            return $p->codigo && strcasecmp(trim($p->codigo), trim($nombreOCodigo)) === 0;
        });
        if ($porCodigo) return $porCodigo;

        // 2) Match exacto por nombre normalizado
        $porNombreExacto = $productos->first(function ($p) use ($entrada) {
            return $this->normalizar($p->nombre) === $entrada;
        });
        if ($porNombreExacto) return $porNombreExacto;

        // 3) Palabras clave (todas las palabras de la entrada deben estar en alguna palabra_clave o nombre)
        $tokens = collect(explode(' ', $entrada))->filter()->values();

        $candidatos = $productos->filter(function ($p) use ($tokens) {
            $bag = collect($p->palabras_clave ?? [])
                ->push($p->nombre)
                ->push($p->descripcion_corta ?? '')
                ->map(fn ($t) => $this->normalizar((string) $t))
                ->join(' ');

            return $tokens->every(fn ($t) => str_contains($bag, $t));
        });

        if ($candidatos->isNotEmpty()) {
            return $candidatos->first();
        }

        // 4) Coincidencia parcial — la entrada está contenida en el nombre o viceversa
        return $productos->first(function ($p) use ($entrada) {
            $nombre = $this->normalizar($p->nombre);
            return str_contains($nombre, $entrada) || str_contains($entrada, $nombre);
        });
    }

    /**
     * Limpia caches del catálogo (llamar cuando cambia un producto/promo/zona).
     */
    public function limpiarCache(): void
    {
        foreach (['all'] as $sufijo) {
            Cache::forget("bot_catalogo_productos_{$sufijo}");
            Cache::forget("bot_promociones_vigentes_{$sufijo}");
            Cache::forget("bot_zonas_activas_{$sufijo}");
        }
        // Y por sede activa
        foreach (\App\Models\Sede::pluck('id') as $id) {
            Cache::forget("bot_catalogo_productos_{$id}");
            Cache::forget("bot_promociones_vigentes_{$id}");
            Cache::forget("bot_zonas_activas_{$id}");
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
