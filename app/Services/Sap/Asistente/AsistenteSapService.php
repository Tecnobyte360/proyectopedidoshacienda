<?php

namespace App\Services\Sap\Asistente;

use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador del Asistente IA + SAP.
 *
 * Toma un mensaje del usuario, se lo pasa al modelo de IA (el que el proyecto
 * tenga configurado: OpenAI o Anthropic, vía AiClientService) junto con las
 * herramientas SAP disponibles, ejecuta las tools que el modelo decida llamar
 * (consultando SAP en vivo) y devuelve la respuesta final en lenguaje natural.
 *
 * Bucle estándar de function-calling (formato OpenAI).
 */
class AsistenteSapService
{
    private const MAX_ITERACIONES = 5;

    public function __construct(
        private AiClientService $ai,
        private SapToolRegistry $tools,
    ) {}

    /**
     * @param  string $mensaje    Pregunta del usuario.
     * @param  array  $historial  Mensajes previos [['role'=>'user|assistant','content'=>...]].
     * @return array{ok:bool, respuesta:string, tools_usadas:array}
     */
    public function responder(string $mensaje, array $historial = []): array
    {
        $tools    = $this->tools->definiciones();
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $this->normalizarHistorial($historial),
            [['role' => 'user', 'content' => $mensaje]],
        );

        $toolsUsadas = [];

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $resp = $this->ai->chat($messages, 'auto', $tools, ['max_tokens' => 1200]);

            if (!$resp) {
                return ['ok' => false, 'respuesta' => 'No pude conectar con el modelo de IA en este momento.', 'tools_usadas' => $toolsUsadas];
            }

            $msg       = $resp['choices'][0]['message'] ?? [];
            $toolCalls = $msg['tool_calls'] ?? [];

            // Sin llamadas a herramientas → respuesta final.
            if (empty($toolCalls)) {
                return [
                    'ok'           => true,
                    'respuesta'    => trim((string) ($msg['content'] ?? '')),
                    'tools_usadas' => $toolsUsadas,
                ];
            }

            // Registramos el turno del asistente (con las tool_calls) y ejecutamos.
            $messages[] = $msg;

            foreach ($toolCalls as $tc) {
                $nombre = $tc['function']['name'] ?? '';
                $args   = $this->decodeArgs($tc['function']['arguments'] ?? '{}');

                $toolsUsadas[] = ['tool' => $nombre, 'args' => $args];

                try {
                    $resultado = $this->tools->ejecutar($nombre, $args);
                } catch (\Throwable $e) {
                    Log::error("Asistente SAP tool {$nombre} falló: " . $e->getMessage());
                    $resultado = ['ok' => false, 'error' => 'excepcion_tool', 'detalle' => $e->getMessage()];
                }

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'] ?? $nombre,
                    'name'         => $nombre,
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return ['ok' => false, 'respuesta' => 'La consulta resultó demasiado compleja (se alcanzó el límite de pasos).', 'tools_usadas' => $toolsUsadas];
    }

    private function decodeArgs(string $json): array
    {
        $a = json_decode($json, true);
        return is_array($a) ? $a : [];
    }

    /** Deja solo role/content de mensajes de usuario/asistente previos. */
    private function normalizarHistorial(array $historial): array
    {
        $out = [];
        foreach ($historial as $m) {
            $role = $m['role'] ?? '';
            if (in_array($role, ['user', 'assistant'], true) && isset($m['content'])) {
                $out[] = ['role' => $role, 'content' => (string) $m['content']];
            }
        }
        return array_slice($out, -20); // últimas 20 intervenciones
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres un asistente analista de VENTAS integrado en tiempo real con SAP Business One.
Respondes en español, claro y ejecutivo, orientado a la acción.

Reglas:
- Para responder cualquier pregunta sobre cotizaciones o pedidos SIEMPRE usa las
  herramientas disponibles; NUNCA inventes cifras ni supongas datos de SAP.
- Si una herramienta devuelve "ok": false o un error, dilo con claridad y no
  inventes: indica que no se pudo consultar SAP en ese momento.
- Sé conciso: primero un titular con la conclusión y las cifras clave, luego, si
  aporta, una lista breve (cliente, número, valor, estado, vencimiento).
- Usa formato de moneda con separador de miles cuando muestres valores.
- Si el usuario no da un rango de fechas o filtro, usa valores por defecto
  razonables y acláralo (p.ej. "cotizaciones de los últimos 30 días").
- No ofrezcas acciones de escritura (crear/editar en SAP): por ahora solo consultas.

Módulo actual: VENTAS (Gestión de Cotizaciones y Análisis de Estados de Pedidos).
PROMPT;
    }
}
