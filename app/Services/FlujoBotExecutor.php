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

            case 'trigger_primer_msg':
                if (!$this->esPrimerMensaje($contexto)) return null;
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

            case 'cond_dia_semana':
                $puerto = $this->cumpleDiaSemana($data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_cliente':
                $puerto = $this->cumpleCliente($contexto['cliente'] ?? null, $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_tiene_pedido':
                $puerto = $this->cumpleTienePedido($contexto['cliente'] ?? null, $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'cond_zona':
                $puerto = $this->cumpleZona($contexto['cliente'] ?? null, $data) ? 'output_1' : 'output_2';
                return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);

            case 'accion_validar_cobertura':
                return $this->ejecutarValidarCobertura($flujo, $nodoId, $data, $contexto, $visitados);

            case 'accion_consultar_pedidos':
                $reply = $this->ejecutarConsultarPedidos($contexto, (int) ($data['limite'] ?? 3));
                return ['handled' => true, 'reply' => $reply, 'short_circuit' => true];

            case 'accion_ans':
                $reply = $this->ejecutarAns($contexto, (string) ($data['accion'] ?? ''));
                return $reply !== null
                    ? ['handled' => true, 'reply' => $reply, 'short_circuit' => true]
                    : $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados);

            case 'accion_imagen_producto':
                $this->ejecutarEnviarImagen($contexto, $data);
                return $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados)
                    ?? ['handled' => true, 'reply' => null, 'short_circuit' => true];

            case 'accion_pasar_ia':
                // No corta el flujo del bot: solo guarda contexto extra para inyectar
                $extra = trim((string) ($data['contexto_extra'] ?? ''));
                if ($extra !== '') {
                    $this->guardarContextoExtra($contexto, $extra);
                }
                return ['handled' => false, 'reply' => null, 'short_circuit' => false];

            case 'accion_derivar':
                return $this->ejecutarDerivar($data, $contexto);

            case 'accion_mensaje':
                $reply = $this->renderizarMensaje((string) ($data['mensaje'] ?? ''), $contexto);
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
                return $this->seguir($flujo, $nodoId, 'output_1', $contexto, $visitados);

            case 'fin':
                return ['handled' => true, 'reply' => null, 'short_circuit' => true];

            default:
                return null;
        }
    }

    /* ─── Nuevos evaluadores ─── */

    private function esPrimerMensaje(array $contexto): bool
    {
        $conv = $contexto['conversacion'] ?? null;
        if (!($conv instanceof ConversacionWhatsapp)) return false;
        // Si solo hay 1 mensaje (el actual del cliente), es el primero.
        try {
            return (int) $conv->mensajes()->count() <= 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function cumpleDiaSemana(array $data): bool
    {
        $dias = $data['dias'] ?? [];
        if (!is_array($dias) || empty($dias)) return false;
        $hoyDow = (int) now('America/Bogota')->dayOfWeek; // 0=Dom..6=Sab
        return in_array($hoyDow, array_map('intval', $dias), true);
    }

    private function cumpleTienePedido($cliente, array $data): bool
    {
        if (!$cliente) return false;
        $tieneActivo = false;
        try {
            $tieneActivo = \App\Models\Pedido::where('cliente_id', $cliente->id)
                ->whereNotIn('estado', ['entregado', 'cancelado'])
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
        return ($data['tipo'] ?? 'con_pedido') === 'sin_pedido' ? !$tieneActivo : $tieneActivo;
    }

    private function cumpleZona($cliente, array $data): bool
    {
        if (!$cliente) return false;
        $zonaId = (int) ($data['zona_id'] ?? 0);
        $zonaCliente = (int) ($cliente->zona_cobertura_id ?? 0);

        if ($zonaId === 0) {
            // "Cualquier zona cubierta"
            return $zonaCliente > 0;
        }
        return $zonaCliente === $zonaId;
    }

    /* ─── Nuevas acciones ─── */

    private function ejecutarValidarCobertura(FlujoBot $flujo, int $nodoId, array $data, array $contexto, array $visitados): ?array
    {
        $modo = (string) ($data['modo'] ?? 'auto');

        if ($modo === 'preguntar') {
            $msg = trim((string) ($data['mensaje_pregunta'] ?? ''))
                ?: 'Cuéntame en qué barrio estás para validar el envío 🙌';
            return ['handled' => true, 'reply' => $this->renderizarMensaje($msg, $contexto), 'short_circuit' => true];
        }

        // Modo auto: intenta extraer dirección/barrio del mensaje
        $mensaje = (string) ($contexto['mensaje'] ?? '');
        $barrio  = $this->extraerBarrio($mensaje);

        if ($barrio === '') {
            // No hay info suficiente, salir por NO
            return $this->seguir($flujo, $nodoId, 'output_2', $contexto, $visitados);
        }

        $sedeId = $contexto['sede_id'] ?? null;
        $zona = \App\Models\ZonaCobertura::resolverPorBarrio($barrio, $sedeId);

        $puerto = $zona ? 'output_1' : 'output_2';
        return $this->seguir($flujo, $nodoId, $puerto, $contexto, $visitados);
    }

    private function extraerBarrio(string $mensaje): string
    {
        // Heurística simple: busca después de "vivo en", "estoy en", "barrio"
        $msg = mb_strtolower($mensaje);
        foreach (['vivo en ', 'estoy en ', 'barrio ', 'soy de '] as $marca) {
            $pos = mb_strpos($msg, $marca);
            if ($pos !== false) {
                $rest = mb_substr($msg, $pos + mb_strlen($marca));
                $rest = preg_replace('/[,.!?].*/', '', $rest);
                return trim($rest);
            }
        }
        return '';
    }

    private function ejecutarConsultarPedidos(array $contexto, int $limite = 3): string
    {
        $cliente = $contexto['cliente'] ?? null;
        if (!$cliente) {
            return 'No encontré tu información — ¿me ayudas con tu nombre y dirección?';
        }

        try {
            $pedidos = \App\Models\Pedido::where('cliente_id', $cliente->id)
                ->orderByDesc('id')
                ->limit(max(1, min(10, $limite)))
                ->get(['id', 'estado', 'total', 'created_at']);
        } catch (\Throwable $e) {
            return 'No pude consultar tu historial en este momento, intenta más tarde 🙏';
        }

        if ($pedidos->isEmpty()) {
            return 'Aún no tienes pedidos con nosotros — ¿qué te gustaría pedir hoy?';
        }

        $primerNombre = trim(explode(' ', (string) $cliente->nombre)[0] ?: 'cliente');
        $lineas = ["Aquí tienes tus últimos pedidos, {$primerNombre} 🙌\n"];
        foreach ($pedidos as $p) {
            $estado = ucfirst(str_replace('_', ' ', (string) $p->estado));
            $total  = '$' . number_format((float) $p->total, 0, ',', '.');
            $fecha  = optional($p->created_at)->format('d/m/Y');
            $lineas[] = "📦 *Pedido #{$p->id}* — {$estado} · {$total} · {$fecha}";
        }
        $lineas[] = "\n¿Te ayudo con algo de uno de estos o quieres pedir algo nuevo?";
        return implode("\n", $lineas);
    }

    private function ejecutarAns(array $contexto, string $accion): ?string
    {
        if ($accion === '') return null;

        $cliente = $contexto['cliente'] ?? null;
        if (!$cliente) return null;

        $regla = \App\Models\AnsPedido::where('accion', $accion)->where('activo', true)->first();
        if (!$regla) return null;

        try {
            $ultimoPedido = \App\Models\Pedido::where('cliente_id', $cliente->id)
                ->whereNotIn('estado', ['entregado', 'cancelado'])
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$ultimoPedido) {
            return "No tienes ningún pedido activo para {$accion}.";
        }

        $minutosTranscurridos = (int) round($ultimoPedido->fecha_pedido->diffInSeconds(now()) / 60);
        $minutosPermitidos    = (int) ($regla->tiempo_minutos ?? 0);

        if ($minutosTranscurridos > $minutosPermitidos) {
            return "Ya pasaron {$minutosTranscurridos} min desde tu pedido #{$ultimoPedido->id} y nuestra ventana para {$accion} es de {$minutosPermitidos} min 🙏. "
                . "Te paso con un asesor para ver qué podemos hacer.";
        }

        $restantes = $minutosPermitidos - $minutosTranscurridos;
        return "Sí podemos {$accion} tu pedido #{$ultimoPedido->id} — te quedan {$restantes} min. "
            . "Cuéntame qué necesitas exactamente.";
    }

    private function ejecutarEnviarImagen(array $contexto, array $data): void
    {
        $productoId = (int) ($data['producto_id'] ?? 0);
        if (!$productoId) return;

        $producto = \App\Models\Producto::find($productoId);
        if (!$producto) return;

        $url = method_exists($producto, 'urlImagen') ? $producto->urlImagen() : null;
        if (!$url) return;

        $caption = $data['caption'] ?? sprintf("*%s*\n💵 $%s/%s",
            $producto->nombre,
            number_format((float) $producto->precio_base, 0, ',', '.'),
            $producto->unidad
        );

        try {
            $sender = app(WhatsappSenderService::class);
            $conv = $contexto['conversacion'] ?? null;
            $connectionId = $conv?->connection_id;
            $from = $contexto['from'] ?? '';
            if ($from) {
                $sender->enviarImagen($from, $url, $caption, $connectionId);
            }
        } catch (\Throwable $e) {
            Log::warning('Flujo: fallo enviar imagen producto: ' . $e->getMessage());
        }
    }

    private function guardarContextoExtra(array $contexto, string $extra): void
    {
        $conv = $contexto['conversacion'] ?? null;
        if (!($conv instanceof ConversacionWhatsapp)) return;

        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $key = "flujo_contexto_extra_t{$tenantId}_conv{$conv->id}";
        \Illuminate\Support\Facades\Cache::put($key, $extra, now()->addMinutes(30));
    }

    /**
     * Lee el contexto extra del flujo (si existe) para inyectarlo en el prompt
     * de la IA en el mismo turno o el siguiente.
     */
    public static function leerContextoExtra(int $conversacionId): ?string
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $key = "flujo_contexto_extra_t{$tenantId}_conv{$conversacionId}";
        $extra = \Illuminate\Support\Facades\Cache::pull($key); // pull = read + delete
        return $extra ?: null;
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
