<?php

namespace App\Services\Bots;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🤝 HANDOFF AUTOMÁTICO A HUMANO
 *
 * Decide cuándo el bot debe transferir la conversación a un agente humano.
 *
 * Triggers:
 *   1. Cliente pidió explícitamente: "asesor", "humano", "persona", "no me entiendes"
 *   2. El bot dio la misma respuesta 3 veces seguidas (loop)
 *   3. > 12 mensajes del cliente sin cerrar pedido
 *   4. Cliente expresa frustración (palabras clave de molestia)
 *   5. El bot detectó que algo grave pasó (error técnico repetido)
 */
class HandoffHumanoService
{
    /**
     * Decide si debemos derivar a humano. Si sí, marca la conversación,
     * notifica y devuelve el mensaje cordial para enviar al cliente.
     *
     * @return string|null Mensaje al cliente si se deriva; null si no.
     */
    public function evaluar(ConversacionWhatsapp $conv, string $mensajeCliente): ?string
    {
        // Si ya está marcada para humano, no repetir
        if ($conv->requiere_humano) return null;

        $msgN = mb_strtolower(Str::ascii(trim($mensajeCliente)));

        // ÚNICA condición que derivamos automáticamente: el cliente lo pide
        // EXPLÍCITAMENTE ("asesor", "humano", "persona real", "agente").
        // Todas las demás heurísticas (frustración, bot en bucle, conversación
        // larga) están DESACTIVADAS para que el LLM tenga libertad total
        // de manejar el flujo. El LLM puede invocar `derivar_a_departamento`
        // conscientemente si lo necesita.
        if ($this->piedeHumano($msgN)) {
            return $this->derivar($conv, 'peticion_explicita',
                'El cliente solicitó hablar con un asesor humano.'
            );
        }

        return null;
    }

    private function piedeHumano(string $msg): bool
    {
        $patrones = [
            '/\b(asesor|asesora|agente|persona\s+real|humano|humana|alguien\s+real)\b/u',
            '/\b(quiero\s+hablar\s+con|necesito\s+hablar\s+con|p[áa]same\s+con|comun[íi]came\s+con)\b/u',
            '/\b(no\s+me\s+entiendes|no\s+me\s+entendes|no\s+entiendes|esto\s+es\s+un\s+robot|eres\s+un\s+robot)\b/u',
            '/\b(otra\s+persona|otro\s+asesor)\b/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $msg)) return true;
        }
        return false;
    }

    private function expresoFrustracion(string $msg): bool
    {
        $patrones = [
            '/\b(no\s+sirve|no\s+funciona|que\s+(verraquera|hp|hpta)|estoy\s+aburrido|estoy\s+aburrida)\b/u',
            '/\b(esto\s+es\s+un\s+desastre|qu[eé]\s+lentitud|qu[eé]\s+demora)\b/u',
            '/\b(d[eé]jame\s+en\s+paz|me\s+aburres|me\s+cans[áa]s)\b/u',
            '/\b(no\s+haces\s+nada|in[úu]til|no\s+entiend[ee]s\s+nada)\b/u',
            // 🆕 Frustración por repetición (el cliente ya dio info y el bot no la usa)
            '/\b(ya\s+(te|le)\s+(hab[ií]a\s+)?(dicho|pedido|coment|expliq))\b/u',
            '/\bte\s+lo\s+(repito|estoy\s+repitiendo|dije\s+ya)\b/u',
            '/\bya\s+lo\s+(dije|te\s+(lo\s+)?dije|coment[eé])\b/u',
            '/\b(esto|eso)\s+ya\s+te\s+lo/u',
            '/\bpor\s+qu[eé]\s+me\s+pides\s+(otra\s+vez|de\s+nuevo|lo\s+mismo)/u',
            '/\bpor\s+qu[eé]\s+(repites|repetis|preguntas)\s+(lo\s+mismo|otra\s+vez)/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $msg)) return true;
        }
        return false;
    }

    private function botEnBucle(ConversacionWhatsapp $conv): bool
    {
        // Últimas 3 respuestas del bot — si son ~iguales, está en bucle
        $ultimas = MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->where('rol', 'assistant')
            ->orderByDesc('id')
            ->limit(3)
            ->pluck('contenido')
            ->all();

        if (count($ultimas) < 3) return false;

        $hashes = array_map(fn ($m) => substr(md5(mb_strtolower(Str::ascii(trim($m)))), 0, 16), $ultimas);
        return count(array_unique($hashes)) === 1;
    }

    private function conversacionDemasiadoLarga(ConversacionWhatsapp $conv): bool
    {
        if ($conv->genero_pedido) return false; // ya cerró pedido

        // Contar SOLO mensajes de la sesión actual (últimas 2h) — evita arrastrar
        // mensajes de visitas anteriores que ya quedaron incompletas.
        $desde = now()->subHours(2);

        $mensajesUserSesion = MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->where('rol', 'user')
            ->where('created_at', '>=', $desde)
            ->count();

        // Umbral más realista: 20 mensajes en 2h sin pedido = bot atascado.
        // Antes era 12 (muy estricto: flujos con varios productos + dirección +
        // cédula + teléfono fácil llegan a 12 mensajes legítimos).
        if ($mensajesUserSesion <= 20) return false;

        // 🛡️ Si el bot está PROGRESANDO (hubo tool_call en los últimos 5 mensajes
        // del bot), NO derivar — el flujo está avanzando normalmente.
        $ultimosBot = MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->where('rol', 'assistant')
            ->where('created_at', '>=', $desde)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['meta']);
        foreach ($ultimosBot as $m) {
            $meta = is_array($m->meta) ? $m->meta : (json_decode((string) $m->meta, true) ?: []);
            $tipo = $meta['tipo'] ?? null;
            if ($tipo === 'tool_call' || $tipo === 'tool_call_dinamica' || !empty($meta['tool_calls'])) {
                return false; // bot ejecutó una tool reciente → progresando
            }
        }

        return true;
    }

    private function derivar(ConversacionWhatsapp $conv, string $motivo, string $razon): string
    {
        try {
            $conv->update([
                'requiere_humano'      => true,
                'humano_motivo'        => $motivo,
                'humano_solicitado_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Fallo al marcar handoff: ' . $e->getMessage());
        }

        Log::warning('🤝 HANDOFF a humano disparado', [
            'conv_id' => $conv->id,
            'motivo'  => $motivo,
            'razon'   => $razon,
        ]);

        // Notificar al admin
        try {
            app(AlertasService::class)->notificar(
                'handoff_humano',
                "🤝 Conversación necesita atención humana",
                "{$razon}\n\nCliente: {$conv->telefono_normalizado}\nConversación #{$conv->id}\n\nRevísala en /chat",
                [
                    'conv_id'  => $conv->id,
                    'telefono' => $conv->telefono_normalizado,
                    'motivo'   => $motivo,
                    'severidad'=> 'alta',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo notificar handoff: ' . $e->getMessage());
        }

        $nombre = explode(' ', trim((string) $conv->cliente?->nombre))[0] ?? '';
        $saludo = $nombre !== '' && !str_contains($nombre, '@') ? " {$nombre}" : '';

        return "Hola{$saludo} 🤝, te conecto con un asesor humano de nuestro equipo "
             . "para que te ayude personalmente. En unos momentos te escribimos por aquí mismo. "
             . "Gracias por tu paciencia.";
    }
}
