<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tools que el bot (agente) puede invocar para CONSULTAR el catálogo
 * en lugar de recibirlo todo dentro del prompt.
 *
 * Reduce el contexto de ~15k tokens a ~2k y mejora precisión drásticamente.
 *
 * Cada metodo público retorna un array JSON-serializable que se envía a OpenAI
 * como respuesta de la tool.
 */
class BotCatalogoToolService
{
    public function __construct(
        private BotCatalogoService $catalogo,
    ) {}

    /**
     * Busca productos por nombre, código o palabras clave.
     * Retorna top N con ranking por relevancia.
     */
    public function buscarProductos(string $query, ?string $categoria = null, int $limite = 5, ?int $sedeId = null): array
    {
        // 🛡️ PALABRAS QUE NO SON PRODUCTOS: si el cliente solo dice "domicilio",
        // "despacho", "envío", "recoger", etc, NO buscar como producto.
        // El catálogo SGI a veces tiene "DOMICILIO" como producto (cargo de envío)
        // y eso causa que el bot facture un cargo cuando el cliente solo está
        // diciendo el MÉTODO DE ENTREGA.
        $qNorm = mb_strtolower(trim($query));
        $palabrasNoProducto = [
            // métodos de entrega
            'domicilio', 'domicilios', 'domiicilio', 'dominicio', 'dominici',
            'despacho', 'despachos',
            'envio', 'envío', 'envios', 'envíos',
            'recoger', 'recogerlo', 'reclamo', 'reclamar',
            'entrega', 'entregas',
            // pago / generales
            'pago', 'efectivo', 'transferencia', 'tarjeta',
            // saludos / despedidas
            'hola', 'buenos dias', 'buenas tardes', 'buenas noches', 'gracias',
            // confirmaciones
            'si', 'no', 'dale', 'listo', 'ok',
        ];

        if (in_array($qNorm, $palabrasNoProducto, true)) {
            \Log::info('🛡️ buscar_productos rechazó palabra que no es producto', [
                'query' => $query,
            ]);
            return [
                'encontrados'  => 0,
                'productos'    => [],
                'sugerencia_bot' => "El término '{$query}' no es un producto del catálogo — parece un método de entrega o palabra funcional. NO la presentes como producto. Si el cliente quiere ese método, captúralo en el flujo (sistema avanzará automáticamente).",
            ];
        }

        // Primer intento: buscar con sede filtrada
        $resultado = $this->buscarInternoConSede($query, $categoria, $limite, $sedeId);

        // 🔄 Fallback: si no encontró NADA con sede, intentar SIN filtrar por sede.
        // Esto resuelve casos donde el operador no asignó sedes a algunos productos
        // (típico en migraciones desde ERP). Mejor mostrar productos de "cualquier
        // sede" que decirle al cliente "no manejo eso".
        // FIX: usar 'encontrados' en vez de empty() (el array siempre tiene claves)
        $sinResultados = ($resultado['encontrados'] ?? 0) === 0;

        if ($sedeId !== null && $sinResultados) {
            $resultado = $this->buscarInternoConSede($query, $categoria, $limite, null);
            $encontrados = $resultado['encontrados'] ?? 0;
            if ($encontrados > 0) {
                \Log::info('🔄 buscar_productos fallback sin sede', [
                    'query'       => $query,
                    'sedeId'      => $sedeId,
                    'encontrados' => $encontrados,
                ]);
                // Marca para que el bot sepa que el producto existe pero no en la sede
                $resultado['nota_sede'] = 'Estos productos existen pero no están asignados a la sede actual del bot. Inclúyelos igualmente en la respuesta.';
            }
        }

        return $resultado;
    }

    private function buscarInternoConSede(string $query, ?string $categoria, int $limite, ?int $sedeId): array
    {
        $productos = $this->catalogo->productosActivos($sedeId);
        $q  = $this->normalizar($query);
        $qF = $this->normalizarFuzzy($query); // tolera typos (dobles letras, etc)
        $cat = $categoria ? $this->normalizar($categoria) : null;

        $candidatos = $productos->filter(function ($p) use ($cat) {
            if (!$cat) return true;
            $catProd = $this->normalizar($this->getCategoriaNombre($p));
            return str_contains($catProd, $cat);
        });

        // Ranking: codigo exacto > nombre exacto > tokens completos > parcial > FUZZY
        $ranked = $candidatos->map(function ($p) use ($q, $qF) {
            $score   = 0;
            $codigo  = (string) ($p->codigo ?? '');
            $nombreN = $this->normalizar((string) ($p->nombre ?? ''));
            $nombreF = $this->normalizarFuzzy((string) ($p->nombre ?? ''));

            if ($q === '' || $q === null) {
                $score = 1;
            } elseif ($codigo !== '' && strcasecmp(trim($codigo), trim($q)) === 0) {
                $score = 100;
            } elseif ($nombreN === $q) {
                $score = 95;
            } else {
                $tokens = collect(explode(' ', $q))->filter()->values();
                $bag = collect($p->palabras_clave ?? [])
                    ->push($p->nombre ?? '')
                    ->push($p->descripcion_corta ?? $p->descripcion ?? '')
                    ->map(fn ($t) => $this->normalizar((string) $t))
                    ->join(' ');
                $bagF = $this->normalizarFuzzy($bag);

                // Tokens completos (todos coinciden literal)
                $hits = $tokens->filter(fn ($t) => str_contains($bag, $t))->count();
                if ($tokens->isNotEmpty() && $hits === $tokens->count()) {
                    $score = 75;
                } elseif ($hits > 0) {
                    $score = 35 + ($hits * 5);
                } elseif (str_contains($nombreN, $q)) {
                    $score = 30;
                } else {
                    // FUZZY: si la version "fuzzy" del query aparece en el bag fuzzy
                    if ($qF !== '' && str_contains($bagF, $qF)) {
                        $score = 50;
                    } else {
                        // Levenshtein por token (tolera 1-2 caracteres distintos)
                        $tokensF = collect(explode(' ', $qF))->filter()->values();
                        $palabrasF = collect(explode(' ', $bagF))->filter()->values();
                        $minDist = PHP_INT_MAX;
                        foreach ($tokensF as $tF) {
                            if (mb_strlen($tF) < 4) continue; // muy cortos no
                            foreach ($palabrasF as $pF) {
                                if (abs(mb_strlen($pF) - mb_strlen($tF)) > 3) continue;
                                $d = levenshtein($tF, $pF);
                                if ($d < $minDist) $minDist = $d;
                            }
                        }
                        if ($minDist <= 1) $score = 45;
                        elseif ($minDist <= 2) $score = 28;
                        elseif ($minDist <= 3) $score = 15;
                    }
                }
            }
            return ['p' => $p, 'score' => $score];
        })
        ->filter(fn ($r) => $r['score'] > 0)
        ->sortByDesc('score')
        ->take($limite)
        ->values();

        $resultado = $ranked->map(fn ($r) => array_merge(
            $this->formatearProducto($r['p'], $sedeId),
            ['_score' => $r['score']]
        ))->all();

        return [
            'encontrados' => count($resultado),
            'query'       => $query,
            'categoria'   => $categoria,
            'productos'   => $resultado,
            'nota'        => count($resultado) > 0
                ? '✓ Productos encontrados que coinciden con "' . $query . '". Si el cliente escribió con typo, los resultados igualmente son válidos — preséntalos como SÍ tenemos ese producto.'
                : 'Sin coincidencias. Sí puedes decir "no manejamos eso".',
        ];
    }

    /**
     * Lista las categorías disponibles con cantidad de productos en cada una.
     */
    public function listarCategorias(?int $sedeId = null): array
    {
        $productos = $this->catalogo->productosActivos($sedeId);

        $cats = $productos->groupBy(fn ($p) => $this->getCategoriaNombre($p))
            ->map(fn ($g, $cat) => [
                'categoria' => $cat ?: 'Otros',
                'cantidad'  => $g->count(),
                'ejemplos'  => $g->take(3)->map(fn ($p) => (string) $p->nombre)->all(),
            ])
            ->values()
            ->all();

        return ['total_categorias' => count($cats), 'categorias' => $cats];
    }

    /**
     * Lista productos de una categoría específica (paginado).
     */
    public function productosDeCategoria(string $categoria, int $limite = 20, ?int $sedeId = null): array
    {
        $productos = $this->catalogo->productosActivos($sedeId);
        $catN = $this->normalizar($categoria);

        $delaCat = $productos
            ->filter(fn ($p) => str_contains($this->normalizar($this->getCategoriaNombre($p)), $catN))
            ->take($limite)
            ->values();

        return [
            'categoria'   => $categoria,
            'encontrados' => $delaCat->count(),
            'productos'   => $delaCat->map(fn ($p) => $this->formatearProducto($p, $sedeId))->all(),
        ];
    }

    /**
     * Información detallada de un producto por código.
     */
    public function infoProducto(string $codigo, ?int $sedeId = null): array
    {
        $productos = $this->catalogo->productosActivos($sedeId);
        $p = $productos->first(function ($x) use ($codigo) {
            return !empty($x->codigo) && strcasecmp(trim((string) $x->codigo), trim($codigo)) === 0;
        });

        if (!$p) {
            return ['encontrado' => false, 'codigo' => $codigo];
        }

        $info = $this->formatearProducto($p, $sedeId);

        // Cortes (solo Eloquent local)
        if ($p instanceof \App\Models\Producto && !empty($p->id)) {
            $info['cortes'] = $p->cortes()->where('activo', true)->pluck('nombre')->all();
            $info['descripcion_completa'] = (string) ($p->descripcion ?? $p->descripcion_corta ?? '');
            $info['imagen_url'] = $p->urlImagen();
            $info['destacado'] = (bool) ($p->destacado ?? false);
        }

        return ['encontrado' => true, 'producto' => $info];
    }

    /**
     * Productos destacados o promociones (cuando el cliente está perdido).
     */
    public function productosDestacados(int $limite = 8, ?int $sedeId = null): array
    {
        $productos = $this->catalogo->productosActivos($sedeId);

        $destacados = $productos->filter(fn ($p) => !empty($p->destacado))
            ->take($limite)
            ->values();

        // Si no hay destacados marcados, tomar primeros productos con precio
        if ($destacados->isEmpty()) {
            $destacados = $productos
                ->filter(fn ($p) => (float) ($p->precio_base ?? 0) > 0)
                ->take($limite)
                ->values();
        }

        $promos = $this->catalogo->promocionesVigentes($sedeId);

        return [
            'destacados'  => $destacados->map(fn ($p) => $this->formatearProducto($p, $sedeId))->all(),
            'promociones' => $promos->map(fn ($pr) => [
                'nombre'      => $pr->nombre,
                'descripcion' => $pr->descripcionCorta(),
                'codigo_cupon' => $pr->codigo_cupon ?: null,
                'fecha_fin'   => $pr->fecha_fin?->format('Y-m-d'),
            ])->all(),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatearProducto($p, ?int $sedeId = null): array
    {
        $precio = method_exists($p, 'precioParaSede')
            ? $p->precioParaSede($sedeId)
            : (float) ($p->precio_base ?? 0);

        return [
            'codigo'    => (string) ($p->codigo ?? ''),
            'nombre'    => (string) ($p->nombre ?? ''),
            'categoria' => $this->getCategoriaNombre($p),
            'precio'    => (float) $precio,
            'unidad'    => (string) ($p->unidad ?? 'unidad'),
            'destacado' => (bool) ($p->destacado ?? false),
        ];
    }

    private function getCategoriaNombre($p): string
    {
        if (is_object($p->categoria ?? null)) return (string) ($p->categoria->nombre ?? '');
        return (string) ($p->categoria ?? '');
    }

    private function normalizar(?string $t): string
    {
        if (!$t) return '';
        $t = mb_strtolower(trim($t));
        $t = Str::ascii($t);
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /**
     * Normalizacion AGRESIVA para tolerar typos comunes:
     *   - Colapsa letras duplicadas: "chicharroon" → "chicharon"
     *   - Quita acentos
     *   - Reemplaza variantes fonéticas: ll→y, qu→k, c+e/i→s, c+a/o/u→k
     *   - Quita espacios y signos
     */
    private function normalizarFuzzy(?string $t): string
    {
        if (!$t) return '';
        $t = $this->normalizar($t);
        // Reemplazos foneticos para español latino
        $t = str_replace(['ll', 'qu', 'gu'], ['y', 'k', 'g'], $t);
        $t = preg_replace('/c([eiy])/', 's$1', $t);
        $t = preg_replace('/c([aou])/', 'k$1', $t);
        $t = str_replace(['z', 'b', 'h'], ['s', 'v', ''], $t);
        // Colapsar letras duplicadas: "oo" → "o", "rr" → "r"
        $t = preg_replace('/(.)\1+/', '$1', $t);
        return $t;
    }
}
