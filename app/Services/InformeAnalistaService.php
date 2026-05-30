<?php

namespace App\Services;

use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\Log;

/**
 * Toma las métricas crudas de InformeNegocioService y le pide a Claude Haiku
 * que escriba un análisis ejecutivo (3-5 párrafos) con insights y
 * recomendaciones accionables — pensado para que lo lea el dueño del negocio
 * (no técnico). NO inventa números: solo interpreta los que se le pasan.
 */
class InformeAnalistaService
{
    public function __construct(private AiClientService $ai) {}

    /**
     * Devuelve un array con:
     *   - 'titular': frase corta (subject candidate)
     *   - 'resumen': 2-3 oraciones (lead)
     *   - 'insights': array de 3-6 bullets con observaciones clave
     *   - 'recomendaciones': array de 2-4 acciones sugeridas
     */
    public function analizar(string $nombreNegocio, array $metricas): array
    {
        $contexto = $this->resumirMetricas($metricas);

        $system = <<<SYS
Sos un analista de negocios senior que escribe informes ejecutivos para
dueños de restaurantes/comercios de Latinoamérica. Lenguaje cercano,
español rioplatense neutro, sin jerga técnica.

REGLAS DURAS:
1. SOLO usá los datos del CONTEXTO. No inventes números, clientes,
   productos ni fechas.
2. Si una métrica NO aparece en el contexto, no la menciones.
3. NO uses emojis decorativos en el cuerpo (sí pueden ir en titulares).
4. NO uses tecnicismos como "tenant", "API", "LLM", "webhook", "wamid".
5. Hablale al dueño como "tu negocio", "tus clientes", "tu equipo".
6. Cada recomendación debe ser CONCRETA Y ACCIONABLE en menos de 1
   semana.

FORMATO de salida (JSON estricto, sin comentarios):
{
  "titular": "frase de 6-10 palabras que capture lo más importante",
  "resumen": "2-3 oraciones que un dueño leería en 15 segundos",
  "insights": ["bullet 1", "bullet 2", "bullet 3", ...],
  "recomendaciones": ["acción 1", "acción 2", ...]
}
SYS;

        $userPrompt = "Negocio: {$nombreNegocio}\n\nCONTEXTO (métricas del período):\n{$contexto}\n\nGenerá el informe en formato JSON.";

        try {
            // OpenAI compatible — el mensaje system entra como primer mensaje del array
            $resp = $this->ai->chat(
                messages: [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                toolChoice: 'none',
                tools: null,
                opts: [
                    'provider'    => 'anthropic',
                    'temperature' => 0.4,
                    'max_tokens'  => 900,
                ],
            );

            $texto = $this->extraerTexto($resp);
            Log::info('🧠 Informe analista IA respuesta', ['texto_inicio' => mb_substr($texto, 0, 200)]);
            $json = $this->extraerJson($texto);

            return [
                'titular'         => $json['titular'] ?? '',
                'resumen'         => $json['resumen'] ?? '',
                'insights'        => array_values((array) ($json['insights'] ?? [])),
                'recomendaciones' => array_values((array) ($json['recomendaciones'] ?? [])),
            ];
        } catch (\Throwable $e) {
            Log::warning('Informe analista IA falló', [
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            return [
                'titular' => '',
                'resumen' => '',
                'insights' => [],
                'recomendaciones' => [],
            ];
        }
    }

    /** Convierte el array de métricas en un texto compacto para meterle al prompt. */
    private function resumirMetricas(array $m): string
    {
        $bloques = [];
        $rango = $m['rango'] ?? null;
        if ($rango) {
            $bloques[] = "Período: {$rango['dias']} día(s), del {$rango['desde']->format('d/m/Y')} al {$rango['hasta']->format('d/m/Y')}.";
        }

        if (!empty($m['volumen'])) {
            $v = $m['volumen'];
            $bloques[] = "Volumen: {$v['convs_nuevas']} conversaciones nuevas, {$v['cliente_msgs']} mensajes del cliente, {$v['operador_msgs']} mensajes del operador, {$v['convs_activas']} conversaciones activas en el período.";
        }

        if (!empty($m['horasPico']['pico']['suma'])) {
            $p = $m['horasPico']['pico'];
            $bloques[] = "Ventana pico de actividad: de las {$p['inicio']}:00 a las {$p['fin']}:00 hs, con {$p['suma']} mensajes de clientes en esa franja.";
        }

        if (($m['tiempoResp']['muestras'] ?? 0) > 0) {
            $t = $m['tiempoResp'];
            $bloques[] = "Tiempo de respuesta del operador (basado en {$t['muestras']} respuestas): promedio {$t['prom_min']} minutos, peor caso {$t['max_min']} minutos.";
        }

        if (!empty($m['sinResponder'])) {
            $n = count($m['sinResponder']);
            $maxHoras = round(max(array_column($m['sinResponder'], 'min_sin_resp')) / 60, 1);
            $bloques[] = "Hay {$n} conversaciones de clientes sin responder hace más de 2 horas. La más antigua lleva {$maxHoras} horas sin respuesta.";
        }

        if (!empty($m['topClientes'])) {
            $tops = array_map(fn($c) => "{$c->telefono} ({$c->total_msgs} msgs)", array_slice($m['topClientes'], 0, 5));
            $bloques[] = "Top clientes más activos: " . implode(', ', $tops) . ".";
        }

        if (!empty($m['palabrasTop'])) {
            $palabras = array_slice(array_keys($m['palabrasTop']), 0, 10);
            $bloques[] = "Palabras más mencionadas por los clientes: " . implode(', ', $palabras) . ".";
        }

        if (!empty($m['reacciones'])) {
            $reacc = array_map(fn($r) => "{$r->emoji}×{$r->n}", array_slice($m['reacciones'], 0, 5));
            $bloques[] = "Reacciones de los clientes a mensajes del negocio: " . implode(', ', $reacc) . ".";
        }

        if (($m['campanas']['total'] ?? 0) > 0) {
            $c = $m['campanas'];
            $bloques[] = "Campañas: se lanzaron {$c['total']} campañas, con {$c['enviados']} mensajes entregados y {$c['fallidos']} fallidos.";
        }

        if (($m['costoMeta']['conversaciones'] ?? 0) > 0) {
            $cm = $m['costoMeta'];
            $bloques[] = "Costo del canal WhatsApp en el período: \${$cm['cop']} COP ({$cm['conversaciones']} conversaciones facturables).";
        }

        return implode("\n", $bloques);
    }

    private function extraerTexto(?array $resp): string
    {
        if (!$resp) return '';
        // AiClientService devuelve formato OpenAI: choices[0].message.content
        return trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
    }

    private function extraerJson(string $texto): array
    {
        // Sacar bloque ```json ... ``` si existe
        if (preg_match('/```(?:json)?\s*([\s\S]+?)```/i', $texto, $m)) {
            $texto = $m[1];
        }
        $texto = trim($texto);
        $data = json_decode($texto, true);
        return is_array($data) ? $data : [];
    }
}
