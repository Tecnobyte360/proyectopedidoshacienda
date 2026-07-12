<?php

namespace App\Services;

use App\Models\ConfiguracionBot;
use App\Models\Producto;
use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\Log;

/**
 * 🧠 AGENTE INTÉRPRETE EXPERTO DE PRODUCTOS
 *
 * Cuando el buscador normal NO encuentra un producto (el cliente usó un nombre
 * coloquial/regional como "cogote", "molida", "punta de anca"...), este agente
 * —un experto carnicero colombiano— deduce a qué producto REAL del catálogo se
 * refiere y lo aprende (lo guarda en palabras_clave para que la próxima vez sea
 * instantáneo, sin volver a llamar a la IA).
 */
class BotInterpreteProductoService
{
    public function __construct(private BotCatalogoService $catalogo) {}

    /**
     * Devuelve el NOMBRE oficial del producto que mejor coincide con el término
     * coloquial, o null si de verdad no aplica ninguno. Además AUTO-APRENDE el
     * sinónimo en el producto (palabras_clave).
     */
    public function interpretarNombre(string $termino, ?int $sedeId = null, ?int $listaPrecio = null): ?string
    {
        $prods = $this->interpretar($termino, $sedeId, $listaPrecio);
        return $prods[0]->nombre ?? null;
    }

    /** @return Producto[]  (hasta 3, del más probable al menos) */
    public function interpretar(string $termino, ?int $sedeId = null, ?int $listaPrecio = null): array
    {
        $termino = trim($termino);
        if (mb_strlen($termino) < 3) return [];

        $productos = $this->catalogo->productosActivos($sedeId, $listaPrecio);
        if ($productos->isEmpty()) return [];

        // Catálogo condensado por categoría: "codigo | NOMBRE"
        $lineas = [];
        foreach ($productos->groupBy(fn ($p) => $p->categoria?->nombre ?? 'Otros') as $cat => $items) {
            $lineas[] = "### {$cat}";
            foreach ($items as $p) {
                $lineas[] = trim((string) $p->codigo) . ' | ' . $p->nombre;
            }
        }
        $catalogoTxt = implode("\n", $lineas);

        $prompt = "Eres un EXPERTO carnicero colombiano. Conoces TODOS los nombres regionales, "
            . "coloquiales y populares de los cortes y productos de carnicería (res, cerdo, pollo, "
            . "pescado, embutidos, vísceras). Ejemplos: 'cogote'=cuello/pescuezo, 'molida'=carne molida, "
            . "'rellena'=morcilla, 'chicharrón'=tocino/papada, 'punta de anca'=colita de cuadril, etc.\n\n"
            . "Te doy el CATÁLOGO de una carnicería (formato: codigo | NOMBRE OFICIAL, agrupado por "
            . "categoría) y un TÉRMINO que dijo un cliente por WhatsApp. Identifica qué producto del "
            . "catálogo quiere el cliente.\n\n"
            . "REGLAS DE RESPUESTA:\n"
            . "- Responde SOLO con los códigos que mejor coinciden, del más probable al menos, "
            . "separados por coma (máximo 3).\n"
            . "- Usa ÚNICAMENTE códigos que existan en el catálogo.\n"
            . "- Si de verdad NINGUNO aplica, responde exactamente: NINGUNO\n"
            . "- No expliques nada. Solo los códigos.\n\n"
            . "TÉRMINO DEL CLIENTE: \"{$termino}\"\n\n"
            . "CATÁLOGO:\n{$catalogoTxt}";

        try {
            $resp = app(AiClientService::class)->chat(
                [['role' => 'user', 'content' => $prompt]],
                'none',
                null,
                ['temperature' => 0, 'max_tokens' => 40]
            );
        } catch (\Throwable $e) {
            Log::warning('🧠 Intérprete de productos: excepción — ' . $e->getMessage());
            return [];
        }

        $texto = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
        if ($texto === '' || stripos($texto, 'NINGUNO') !== false) {
            Log::info('🧠 Intérprete: sin match para "' . $termino . '"');
            return [];
        }

        $codigos = collect(preg_split('/[,\s]+/', $texto))
            ->map(fn ($c) => trim($c))->filter()->take(3)->values()->all();

        $match = $productos
            ->filter(fn ($p) => in_array((string) $p->codigo, $codigos, true))
            ->sortBy(fn ($p) => array_search((string) $p->codigo, $codigos, true))
            ->values();

        if ($match->isEmpty()) return [];

        Log::info('🧠✅ Intérprete resolvió "' . $termino . '"', [
            'codigos' => $codigos,
            'productos' => $match->pluck('nombre')->all(),
        ]);

        // 🧠 AUTO-APRENDER: guardar el término en palabras_clave del TOP match.
        $this->autoAprender($match->first(), $termino);

        return $match->all();
    }

    /** Agrega el término coloquial a las palabras_clave del producto (idempotente). */
    private function autoAprender(Producto $p, string $termino): void
    {
        try {
            $t = mb_strtolower(trim($termino));
            if ($t === '' || empty($p->id)) return;
            // 🛡️ Recargar el modelo FRESCO por id: el producto que llega viene del
            //    catálogo híbrido "live" con atributos virtuales (_fuente, etc.) que
            //    NO son columnas → guardarlo directo rompe el UPDATE.
            $fresh = Producto::find($p->id);
            if (!$fresh) return;
            $actuales = collect($fresh->palabras_clave ?? [])->map(fn ($x) => mb_strtolower(trim((string) $x)));
            if ($actuales->contains($t)) return;
            $fresh->palabras_clave = $actuales->push($t)->unique()->values()->all();
            $fresh->saveQuietly();
            Log::info('🧠📚 Sinónimo APRENDIDO', ['producto' => $fresh->nombre, 'termino' => $t]);
        } catch (\Throwable $e) {
            Log::warning('autoAprender sinónimo falló: ' . $e->getMessage());
        }
    }
}
