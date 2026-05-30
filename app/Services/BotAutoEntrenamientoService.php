<?php

namespace App\Services;

use App\Models\BotLeccion;
use App\Models\ConversacionWhatsapp;
use App\Models\Tenant;
use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 🧠 Motor de auto-entrenamiento del bot.
 *
 * Analiza las conversaciones REALES entre clientes y operadores humanos,
 * y destila "lecciones" (tono, FAQ, precios, reglas de negocio, manejo de
 * objeciones, escalamiento) usando la IA. Las guarda en la tabla
 * bot_lecciones (la misma que ve el usuario en "Lecciones del bot" y que
 * ya se inyecta al system prompt), marcadas como fuente automática.
 *
 * Es ACUMULATIVO: si una lección similar ya existe, sube veces_aplicada
 * y la mantiene activa, sin duplicar.
 */
class BotAutoEntrenamientoService
{
    public function __construct(private AiClientService $ai) {}

    /** Categorías legibles para clasificar lecciones. */
    private const CATEGORIAS = [
        'Tono y trato', 'Preguntas frecuentes', 'Precios', 'Productos',
        'Reglas del negocio', 'Manejo de objeciones', 'Escalamiento a humano',
        'Cobertura y domicilios', 'Medios de pago', 'Flujo del pedido',
    ];

    public function entrenar(Tenant $tenant, bool $full = false, int $diasVentana = 2): array
    {
        app(TenantManager::class)->set($tenant);
        $tid = $tenant->id;

        $convQuery = ConversacionWhatsapp::withoutGlobalScopes()->where('tenant_id', $tid);
        if (!$full) {
            $convQuery->where('ultimo_mensaje_at', '>=', now()->subDays($diasVentana));
        }
        $convIds = $convQuery->orderByDesc('ultimo_mensaje_at')->pluck('id')->all();

        if (empty($convIds)) {
            return ['convs_analizadas' => 0, 'lotes' => 0, 'lecciones_nuevas' => 0, 'lecciones_reforzadas' => 0];
        }

        $lotes = array_chunk($convIds, 15);
        $nuevas = 0; $reforzadas = 0; $analizadas = 0;

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
                'tenant' => $tenant->slug, 'convs' => count($loteIds), 'lecciones' => count($lecciones),
            ]);
        }

        return [
            'convs_analizadas'     => $analizadas,
            'lotes'                => count($lotes),
            'lecciones_nuevas'     => $nuevas,
            'lecciones_reforzadas' => $reforzadas,
        ];
    }

    private function construirTranscripcion(array $convIds): string
    {
        $out = [];
        $msgs = DB::table('mensajes_whatsapp')
            ->whereIn('conversacion_id', $convIds)
            ->orderBy('conversacion_id')->orderBy('id')
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
            if ($who === 'BOT') continue;
            $tipo = ($m->tipo && $m->tipo !== 'text') ? "[{$m->tipo}] " : '';
            $cont = trim(preg_replace('/\s+/', ' ', (string) $m->contenido));
            $cont = mb_substr($cont, 0, 300);
            if ($cont === '') continue;
            $out[] = "{$who}: {$tipo}{$cont}";
        }
        return implode("\n", $out);
    }

    private function extraerLeccionesIA(string $negocio, string $transcripcion): array
    {
        $cats = implode(', ', self::CATEGORIAS);

        $system = <<<SYS
Sos un analista experto en atención al cliente que entrena asistentes
virtuales. Lee conversaciones REALES entre clientes y operadores humanos
de "{$negocio}" y extraé LECCIONES concretas para que un bot futuro
responda igual de bien que el operador humano.

QUÉ EXTRAER (patrones repetidos, no casos únicos):
- Tono y forma de hablar del operador (saludos, cierres, muletillas).
- Reglas de negocio (precios, mínimos, costos/tiempos de domicilio).
- FAQ: preguntas que se repiten y su respuesta correcta.
- Manejo de objeciones (precio, medios de pago, demoras).
- Cuándo escalar/derivar (otra sede, reclamos).
- Datos del negocio (productos, disponibilidad, medios de pago).

REGLAS DURAS:
1. SOLO lo que aparece en las conversaciones. NO inventes precios/productos.
2. Cada lección ACCIONABLE y reusable por un bot.
3. NO incluyas datos personales (nombres, cédulas, teléfonos, direcciones).
4. categoria debe ser una de: {$cats}
5. confianza entre 0.5 y 0.95 según qué tan repetido está el patrón.

FORMATO (JSON array estricto, sin texto extra):
[
  {
    "categoria":"una de la lista",
    "titulo":"resumen corto de la leccion (max 60 chars)",
    "patron":"que situacion dispara esta leccion",
    "leccion":"instruccion clara e imperativa para el bot",
    "ejemplo_bueno":"frase modelo del operador (sin datos personales)",
    "palabras_clave":"palabras separadas por coma que activan la leccion",
    "confianza":0.8
  }
]
Extraé entre 3 y 12 lecciones de ALTA calidad.
SYS;

        $userPrompt = "CONVERSACIONES:\n\n{$transcripcion}\n\nExtraé las lecciones en JSON.";

        try {
            $resp = $this->ai->chat(
                messages: [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                toolChoice: 'none',
                tools: null,
                opts: ['provider' => 'anthropic', 'temperature' => 0.3, 'max_tokens' => 2500],
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
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/i', $texto, $m)) $texto = $m[1];
        $ini = strpos($texto, '['); $fin = strrpos($texto, ']');
        if ($ini !== false && $fin !== false && $fin > $ini) $texto = substr($texto, $ini, $fin - $ini + 1);
        $data = json_decode(trim($texto), true);
        return is_array($data) ? $data : [];
    }

    /** @return string 'nueva'|'reforzada'|'descartada' */
    private function guardarLeccion(int $tid, array $lec): string
    {
        $categoria = $lec['categoria'] ?? null;
        $leccion   = trim($lec['leccion'] ?? '');
        $titulo    = trim($lec['titulo'] ?? '');
        $patron    = trim($lec['patron'] ?? '');
        $confianza = max(0.5, min(0.95, (float) ($lec['confianza'] ?? 0.6)));

        if ($leccion === '' || !in_array($categoria, self::CATEGORIAS, true)) return 'descartada';
        if ($titulo === '') $titulo = mb_substr($leccion, 0, 60);

        // Prioridad derivada de la confianza (compatible con columnas string o int)
        $prioridad = $confianza >= 0.8 ? 'alta' : ($confianza >= 0.65 ? 'media' : 'baja');

        // Buscar lección similar existente para reforzar (acumulativo)
        $existente = BotLeccion::query()->withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('categoria', $categoria)
            ->get()
            ->first(function ($l) use ($leccion) {
                similar_text(mb_strtolower($l->leccion_aprendida ?? ''), mb_strtolower($leccion), $pct);
                return $pct > 75;
            });

        if ($existente) {
            $upd = ['activa' => true];
            if (Schema::hasColumn('bot_lecciones', 'veces_aplicada')) {
                $upd['veces_aplicada'] = (int) ($existente->veces_aplicada ?? 0) + 1;
            }
            $existente->update($upd);
            return 'reforzada';
        }

        // Construir payload solo con columnas que existan (defensivo)
        $payload = ['tenant_id' => $tid];
        $candidatos = [
            'categoria'         => $categoria,
            'titulo'            => mb_substr($titulo, 0, 120),
            'error_detectado'   => mb_substr($patron, 0, 500) ?: null,
            'leccion_aprendida' => mb_substr($leccion, 0, 1000),
            'ejemplo_correcto'  => mb_substr(trim($lec['ejemplo_bueno'] ?? ''), 0, 500) ?: null,
            'palabras_clave'    => mb_substr(trim($lec['palabras_clave'] ?? ''), 0, 255) ?: null,
            'prioridad'         => $prioridad,
            'activa'            => true,
            'veces_aplicada'    => 0,
            'creado_por'        => null,
        ];
        foreach ($candidatos as $col => $val) {
            if (Schema::hasColumn('bot_lecciones', $col)) $payload[$col] = $val;
        }

        try {
            BotLeccion::create($payload);
            return 'nueva';
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar lección auto', ['error' => $e->getMessage(), 'payload' => array_keys($payload)]);
            return 'descartada';
        }
    }
}
