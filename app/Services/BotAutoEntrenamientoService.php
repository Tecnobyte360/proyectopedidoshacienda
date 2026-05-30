<?php

namespace App\Services;

use App\Models\BotLeccionAprendida;
use App\Models\ConversacionWhatsapp;
use App\Models\Tenant;
use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🧠 Motor de auto-entrenamiento del bot.
 *
 * Analiza las conversaciones REALES entre clientes y operadores humanos,
 * y destila "lecciones aprendidas" (tono, FAQ, precios, reglas de negocio,
 * manejo de objeciones, casos de escalamiento) usando la IA.
 *
 * Las lecciones se guardan en bot_lecciones_aprendidas y BotPromptService
 * ya las inyecta automáticamente al system prompt (capaLeccionesAprendidas).
 *
 * Es ACUMULATIVO: si una lección similar ya existe, sube veces_detectado
 * y refuerza la confianza, sin duplicar.
 */
class BotAutoEntrenamientoService
{
    public function __construct(private AiClientService $ai) {}

    /** Categorías válidas para clasificar lecciones. */
    private const CATEGORIAS = [
        'tono', 'faq', 'precio', 'producto', 'regla_negocio',
        'objecion', 'escalamiento', 'cobertura', 'pago', 'flujo_pedido',
    ];

    /**
     * Entrena con conversaciones del tenant.
     *
     * @param  bool $full  true = TODAS las conversaciones; false = solo las
     *                     con actividad desde $desde (entrenamiento diario).
     * @param  int  $diasVentana  cuando no es full, cuántos días atrás mirar.
     * @return array{convs_analizadas:int, lotes:int, lecciones_nuevas:int, lecciones_reforzadas:int}
     */
    public function entrenar(Tenant $tenant, bool $full = false, int $diasVentana = 2): array
    {
        app(TenantManager::class)->set($tenant);
        $tid = $tenant->id;

        // 1. Conversaciones a analizar
        $convQuery = ConversacionWhatsapp::withoutGlobalScopes()
            ->where('tenant_id', $tid);

        if (!$full) {
            $convQuery->where('ultimo_mensaje_at', '>=', now()->subDays($diasVentana));
        }

        $convIds = $convQuery->orderByDesc('ultimo_mensaje_at')->pluck('id')->all();

        if (empty($convIds)) {
            return ['convs_analizadas' => 0, 'lotes' => 0, 'lecciones_nuevas' => 0, 'lecciones_reforzadas' => 0];
        }

        // 2. Procesar en lotes (15 conversaciones por llamada IA, para no exceder tokens)
        $lotes = array_chunk($convIds, 15);
        $nuevas = 0;
        $reforzadas = 0;
        $analizadas = 0;

        foreach ($lotes as $i => $loteIds) {
            $transcripcion = $this->construirTranscripcion($loteIds);
            if (trim($transcripcion) === '') continue;

            $lecciones = $this->extraerLeccionesIA($tenant->nombre, $transcripcion);

            foreach ($lecciones as $lec) {
                $res = $this->guardarLeccion($tid, $lec);
                if ($res === 'nueva') $nuevas++;
                elseif ($res === 'reforzada') $reforzadas++;
            }

            $analizadas += count($loteIds);
            Log::info("🧠 Auto-entrenamiento lote " . ($i + 1) . "/" . count($lotes), [
                'tenant' => $tenant->slug,
                'convs'  => count($loteIds),
                'lecciones_extraidas' => count($lecciones),
            ]);
        }

        return [
            'convs_analizadas'     => $analizadas,
            'lotes'                => count($lotes),
            'lecciones_nuevas'     => $nuevas,
            'lecciones_reforzadas' => $reforzadas,
        ];
    }

    /** Arma el texto de las conversaciones del lote para mandar a la IA. */
    private function construirTranscripcion(array $convIds): string
    {
        $out = [];
        $msgs = DB::table('mensajes_whatsapp')
            ->whereIn('conversacion_id', $convIds)
            ->orderBy('conversacion_id')
            ->orderBy('id')
            ->get(['conversacion_id', 'rol', 'tipo', 'contenido', 'meta']);

        $convActual = null;
        foreach ($msgs as $m) {
            if ($m->conversacion_id !== $convActual) {
                $out[] = "\n--- Conversación #{$m->conversacion_id} ---";
                $convActual = $m->conversacion_id;
            }
            $meta = json_decode($m->meta ?? '{}', true);
            $who = $m->rol === 'user'
                ? 'CLIENTE'
                : (($meta['enviado_por_humano'] ?? false) ? 'OPERADOR' : 'BOT');
            // Saltar mensajes de plantillas/bot de prueba para no contaminar
            if ($who === 'BOT') continue;
            $tipo = ($m->tipo && $m->tipo !== 'text') ? "[{$m->tipo}] " : '';
            $cont = trim(preg_replace('/\s+/', ' ', (string) $m->contenido));
            $cont = mb_substr($cont, 0, 300);
            if ($cont === '') continue;
            $out[] = "{$who}: {$tipo}{$cont}";
        }
        return implode("\n", $out);
    }

    /** Llama a la IA para extraer lecciones estructuradas del lote. */
    private function extraerLeccionesIA(string $negocio, string $transcripcion): array
    {
        $cats = implode(', ', self::CATEGORIAS);

        $system = <<<SYS
Sos un analista experto en atención al cliente que entrena asistentes
virtuales. Tu trabajo es leer conversaciones REALES entre clientes y
operadores humanos de "{$negocio}" y extraer LECCIONES concretas para que
un bot futuro responda igual de bien que el operador humano.

QUÉ EXTRAER (busca patrones repetidos, no casos únicos):
- Tono y forma de hablar del operador (saludos, cierres, muletillas).
- Reglas de negocio (precios, mínimos, costos de domicilio, tiempos).
- FAQ: preguntas que se repiten y su respuesta correcta.
- Manejo de objeciones (precio, medios de pago, demoras).
- Cuándo el operador escala o deriva (otra sede, reclamos).
- Datos del negocio (productos, disponibilidad, métodos de pago).

REGLAS DURAS:
1. SOLO extraé lo que aparece en las conversaciones. NO inventes precios,
   productos ni reglas que no veas explícitos.
2. Cada lección debe ser ACCIONABLE y reusable por un bot.
3. NO incluyas datos personales de clientes (nombres, cédulas, teléfonos,
   direcciones). Generalizá.
4. categoria debe ser una de: {$cats}
5. confianza entre 0.5 y 0.95 según qué tan repetido/claro está el patrón.

FORMATO de salida (JSON estricto, array, sin texto extra):
[
  {
    "categoria": "una de la lista",
    "patron_detectado": "qué situación dispara esta lección (corto)",
    "leccion": "instrucción clara para el bot, imperativa",
    "ejemplo_bueno": "frase modelo que diría el operador (sin datos personales)",
    "confianza": 0.8
  }
]

Extraé entre 3 y 12 lecciones de ALTA calidad. Si el lote es pobre, devolvé menos.
SYS;

        $userPrompt = "CONVERSACIONES A ANALIZAR:\n\n{$transcripcion}\n\nExtraé las lecciones en JSON.";

        try {
            $resp = $this->ai->chat(
                messages: [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                toolChoice: 'none',
                tools: null,
                opts: [
                    'provider'    => 'anthropic',
                    'temperature' => 0.3,
                    'max_tokens'  => 2500,
                ],
            );

            $texto = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
            return $this->parseJsonArray($texto);
        } catch (\Throwable $e) {
            Log::warning('Auto-entrenamiento IA falló en lote', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function parseJsonArray(string $texto): array
    {
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/i', $texto, $m)) {
            $texto = $m[1];
        }
        // Recortar al primer [ ... ] por si la IA agregó texto
        $ini = strpos($texto, '[');
        $fin = strrpos($texto, ']');
        if ($ini !== false && $fin !== false && $fin > $ini) {
            $texto = substr($texto, $ini, $fin - $ini + 1);
        }
        $data = json_decode(trim($texto), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Guarda o refuerza una lección. Acumulativo: si ya existe una lección
     * muy parecida (misma categoría + patrón similar), sube veces_detectado.
     *
     * @return string 'nueva' | 'reforzada' | 'descartada'
     */
    private function guardarLeccion(int $tid, array $lec): string
    {
        $categoria = $lec['categoria'] ?? null;
        $leccion   = trim($lec['leccion'] ?? '');
        $patron    = trim($lec['patron_detectado'] ?? '');
        $confianza = (float) ($lec['confianza'] ?? 0.6);

        if ($leccion === '' || !in_array($categoria, self::CATEGORIAS, true)) {
            return 'descartada';
        }
        $confianza = max(0.5, min(0.95, $confianza));

        // Buscar lección similar existente (misma categoría + patrón parecido)
        $existente = BotLeccionAprendida::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('categoria', $categoria)
            ->get()
            ->first(function ($l) use ($patron, $leccion) {
                similar_text(mb_strtolower($l->patron_detectado ?? ''), mb_strtolower($patron), $pctPatron);
                similar_text(mb_strtolower($l->leccion ?? ''), mb_strtolower($leccion), $pctLec);
                return $pctPatron > 70 || $pctLec > 75;
            });

        if ($existente) {
            // Reforzar: sube contador y confianza (cap 0.95)
            $existente->update([
                'veces_detectado' => (int) $existente->veces_detectado + 1,
                'confianza'       => min(0.95, (float) $existente->confianza + 0.05),
                'activa'          => true,
            ]);
            return 'reforzada';
        }

        BotLeccionAprendida::create([
            'tenant_id'        => $tid,
            'categoria'        => $categoria,
            'patron_detectado' => mb_substr($patron, 0, 255),
            'leccion'          => mb_substr($leccion, 0, 1000),
            'ejemplo_bueno'    => mb_substr(trim($lec['ejemplo_bueno'] ?? ''), 0, 500) ?: null,
            'ejemplo_malo'     => mb_substr(trim($lec['ejemplo_malo'] ?? ''), 0, 500) ?: null,
            'veces_detectado'  => 1,
            'confianza'        => $confianza,
            'activa'           => true,
            'fuente'           => 'auto_conversaciones',
            'meta'             => null,
        ]);
        return 'nueva';
    }
}
