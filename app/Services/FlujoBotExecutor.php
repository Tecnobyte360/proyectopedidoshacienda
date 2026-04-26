<?php

namespace App\Services;

use App\Models\ConversacionWhatsapp;
use App\Models\Departamento;
use App\Models\FlujoBot;
use App\Models\Sede;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta un flujo visual sobre un mensaje entrante.
 *
 * El grafo se almacena en formato Drawflow:
 *   { drawflow: { Home: { data: { ID: { id, name, data, inputs, outputs, ... }, ... } } } }
 *
 * Tipos de nodo soportados:
 *   trigger          → entry point
 *   cond_palabras    → 2 salidas: output_1=SI, output_2=NO
 *   cond_intencion   → 2 salidas: output_1=SI, output_2=NO (evalúa con OpenAI)
 *   cond_horario     → 2 salidas según sede->estaAbierta()
 *   cond_cliente     → 2 salidas según cliente nuevo o recurrente
 *   accion_derivar   → 1 salida (efecto: derivar a depto + corta el bot)
 *   accion_mensaje   → 1 salida (efecto: respuesta directa al cliente)
 *   accion_etiquetar → 1 salida (efecto: pone etiqueta en conversación)
 *   accion_esperar   → 1 salida (efecto: TODO — encola job de seguimiento)
 *   fin              → termina la ejecución
 */
class FlujoBotExecutor
{
    /**
     * Recorre flujos activos del tenant y ejecuta el primero que coincida.
     *
     * @param array $contexto ['mensaje', 'cliente', 'conversacion', 'sede_id', 'name', 'from']
     * @return array|null ['handled' => bool, 'reply' => ?string, 'short_circuit' => bool]
     */
    public function ejecutarFlujosActivos(array $contexto): ?array
    {
        $flujos = FlujoBot::where('activo', true)
            ->orderByDesc('prioridad')
            ->orderBy('id')
            ->get();

        foreach ($flujos as $flujo) {
            $resultado = $this->ejecutar($flujo, $contexto);
            if ($resultado && ($resultado['handled'] ?? false)) {
                Log::info('🔀 Flujo aplicado', [
                    'flujo'    => $flujo->nombre,
                    'mensaje'  => mb_substr($contexto['mensaje'] ?? '', 0, 60),
                ]);
                return $resultado;
            }
        }

        return null;
    }

    public function ejecutar(FlujoBot $flujo, array $contexto): ?array
    {
        $trigger = $flujo->nodoTrigger();
        if (!$trigger) return null;

        return $this->recorrer($flujo, (int) $trigger['id'], $contexto, []);
    }

    private function recorrer(FlujoBot $flujo, int $nodoId, array $contexto, array $visitados): ?array
    {
        if (in_array($nodoId, $visitados, true)) {
            return null; // protección anti-loop
        }
        $visitados[] = $nodoId;

        $nodo = $flujo->nodoPorId($nodoId);
        if (!$nodo) return null;

        $tipo = $nodo['data']['tipo'] ?? null;
        $data = $nodo['data'] ?? [];

        switch ($tipo) {
            case 'trigger':
                return $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados);

            case 'cond_palabras':
                $puerto = $this->cumplePalabras($contexto['mensaje'] ?? '', $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_intencion':
                $puerto = $this->cumpleIntencion($contexto['mensaje'] ?? '', $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_horario':
                $puerto = $this->cumpleHorario($contexto['sede_id'] ?? null, $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_cliente':
                $puerto = $this->cumpleCliente($contexto['cliente'] ?? null, $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'accion_derivar':
                return $this->ejecutarDerivar($data, $contexto);

            case 'accion_mensaje':
                $reply = $this->renderizarMensaje((string) ($data['mensaje'] ?? ''), $contexto);
                // Si tiene siguiente (raro), seguimos; si no, retornamos directo
                $next = $flujo->nodosSiguientes($nodoId, 'output_1');
                if (!empty($next)) {
                    return $this->recorrer($flujo, (int) $next[0]['id'], $contexto, $visitados) ?? [
                        'handled' => true, 'reply' => $reply, 'short_circuit' => true,
                    ];
                }
                return ['handled' => true, 'reply' => $reply, 'short_circuit' => true];

            case 'accion_etiquetar':
                $this->ejecutarEtiquetar($contexto, (string) ($data['etiqueta'] ?? ''));
                return $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados)
                    ?? ['handled' => false, 'reply' => null, 'short_circuit' => false];

            case 'accion_esperar':
                // TODO: encolar job de seguimiento. Por ahora seguimos al siguiente.
                return $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados);

            case 'fin':
                return ['handled' => true, 'reply' => null, 'short_circuit' => true];

            default:
                return null;
        }
    }

    private function seguir(FlujoBot $flujo, int $nodoId, string $puerto, array $contexto, array $visitados): ?array
    {
        $siguientes = $flujo->nodosSiguientes($nodoId, $puerto);
        if (empty($siguientes)) return null;
        return $this->recorrer($flujo, (int) $siguientes[0]['id'], $contexto, $visitados);
    }

    /* ─── Evaluadores de condición ─── */

    private function cumplePalabras(string $mensaje, array $data): bool
    {
        $palabras = trim((string) ($data['palabras'] ?? ''));
        if ($palabras === '') return false;

        $cs = (bool) ($data['case_sensitive'] ?? false);
        $hay = $cs ? $mensaje : mb_strtolower($mensaje);

        foreach (explode(',', $palabras) as $p) {
            $p = trim($cs ? $p : mb_strtolower($p));
            if ($p === '') continue;
            if (mb_strpos($hay, $p) !== false) return true;
        }
        return false;
    }

    private function cumpleIntencion(string $mensaje, array $data): bool
    {
        $intencion = trim((string) ($data['intencion'] ?? ''));
        if ($intencion === '' || $mensaje === '') return false;

        try {
            $apiKey = \App\Models\Tenant::resolverOpenaiKey();
            if (!$apiKey) return false;

            $resp = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o-mini',
                    'temperature' => 0,
                    'max_tokens'  => 5,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Eres un clasificador. Responde SOLO con "SI" o "NO" (sin más texto). Evalúa si el mensaje del cliente encaja con la intención dada.'],
                        ['role' => 'user',   'content' => "Intención: {$intencion}\n\nMensaje del cliente: \"{$mensaje}\"\n\n¿Encaja? SI/NO"],
                    ],
                ]);

            if (!$resp->successful()) return false;
            $out = mb_strtoupper(trim((string) $resp->json('choices.0.message.content', '')));
            return str_starts_with($out, 'SI') || str_starts_with($out, 'YES');
        } catch (\Throwable $e) {
            Log::warning('Flujo cond_intencion falló: ' . $e->getMessage());
            return false;
        }
    }

    private function cumpleHorario(?int $sedeId, array $data): bool
    {
        $sede = $sedeId ? Sede::find($sedeId) : Sede::query()->first();
        if (!$sede) return false;
        $estado = (string) ($data['estado'] ?? 'abierta');
        return $estado === 'abierta' ? $sede->estaAbierta() : !$sede->estaAbierta();
    }

    private function cumpleCliente($cliente, array $data): bool
    {
        if (!$cliente) return false;
        $tipo = (string) ($data['tipo'] ?? 'nuevo');
        $totalPedidos = (int) ($cliente->total_pedidos ?? 0);
        return $tipo === 'recurrente' ? $totalPedidos > 0 : $totalPedidos === 0;
    }

    /* ─── Acciones ─── */

    private function ejecutarDerivar(array $data, array $contexto): array
    {
        $deptoId = (int) ($data['departamento_id'] ?? 0);
        $razon   = (string) ($data['razon'] ?? 'Derivado por flujo');

        if (!$deptoId) {
            Log::warning('Flujo accion_derivar sin departamento_id');
            return ['handled' => false, 'reply' => null, 'short_circuit' => false];
        }

        $depto = Departamento::find($deptoId);
        if (!$depto || !$depto->activo) {
            Log::warning('Flujo: departamento inactivo o no encontrado', ['id' => $deptoId]);
            return ['handled' => false, 'reply' => null, 'short_circuit' => false];
        }

        $conv = $contexto['conversacion'] ?? null;
        if ($conv instanceof ConversacionWhatsapp) {
            $conv->update([
                'departamento_id'     => $depto->id,
                'derivada_at'         => now(),
                'atendida_por_humano' => true,
            ]);

            // Notificar al equipo del departamento
            if ($depto->notificar_internos) {
                try {
                    $usuarios = \App\Models\UsuarioInternoWhatsapp::withoutGlobalScopes()
                        ->where('tenant_id', $depto->tenant_id)
                        ->where('departamento_id', $depto->id)
                        ->where('activo', true)
                        ->get();

                    $name    = $contexto['name'] ?? 'Cliente';
                    $from    = $contexto['from'] ?? '';
                    $message = $contexto['mensaje'] ?? '';

                    $texto = "🔀 *Derivación por flujo a {$depto->nombre}*\n\n"
                           . "👤 *Cliente:* {$name}\n"
                           . "📞 *Teléfono:* {$from}\n\n"
                           . "💬 *Razón:* {$razon}\n\n"
                           . "📝 *Mensaje:*\n" . mb_strimwidth($message, 0, 250, '…');

                    $sender = app(WhatsappSenderService::class);
                    foreach ($usuarios as $u) {
                        $sender->enviarTexto($u->telefono_normalizado, $texto, $conv->connection_id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Flujo: fallo notificar derivación: ' . $e->getMessage());
                }
            }
        }

        $reply = $depto->saludo_automatico
            ?: "Voy a pasarte con un asesor de *{$depto->nombre}* — en un momento te atienden 🙏";

        return ['handled' => true, 'reply' => $reply, 'short_circuit' => true];
    }

    private function ejecutarEtiquetar(array $contexto, string $etiqueta): void
    {
        $etiqueta = trim($etiqueta);
        if ($etiqueta === '') return;

        $conv = $contexto['conversacion'] ?? null;
        if (!($conv instanceof ConversacionWhatsapp)) return;

        try {
            $tags = $conv->etiquetas ?? [];
            if (!is_array($tags)) $tags = [];
            if (!in_array($etiqueta, $tags, true)) {
                $tags[] = $etiqueta;
                $conv->etiquetas = $tags;
                $conv->save();
            }
        } catch (\Throwable $e) {
            // La columna puede no existir aún — no bloqueamos
            Log::warning('Flujo: fallo etiquetar conversacion: ' . $e->getMessage());
        }
    }

    private function renderizarMensaje(string $tpl, array $contexto): string
    {
        $cliente = $contexto['cliente'] ?? null;
        $sedeId  = $contexto['sede_id'] ?? null;
        $sede    = $sedeId ? Sede::find($sedeId) : null;

        $nombre = $cliente?->nombre ?? ($contexto['name'] ?? 'cliente');
        $primerNombre = trim(explode(' ', (string) $nombre)[0] ?: $nombre);

        return strtr($tpl, [
            '{nombre}' => $primerNombre,
            '{nombre_completo}' => $nombre,
            '{sede}'   => $sede?->nombre ?? '',
            '{hora}'   => now('America/Bogota')->format('h:i a'),
            '{fecha}'  => now('America/Bogota')->locale('es')->isoFormat('D [de] MMMM'),
        ]);
    }
}
