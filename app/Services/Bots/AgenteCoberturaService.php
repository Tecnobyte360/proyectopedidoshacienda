<?php

namespace App\Services\Bots;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Models\Sede;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🗺️ AGENTE ESPECIALIZADO EN VALIDACIÓN DE COBERTURA
 *
 * Servicio determinista que se invoca cuando el cliente da una dirección
 * para despacho. Su único trabajo:
 *
 *   1. Tomar la dirección, barrio y ciudad del estado.
 *   2. Validar cobertura llamando al método interno del controller
 *      (que hace geocoding + cálculo de distancia a sedes).
 *   3. Persistir el resultado en el estado.
 *   4. Devolver una decisión clara al Router:
 *        - 'cubierta'           → OK, continuar flujo
 *        - 'fuera_de_cobertura' → respuesta hardcoded ofreciendo sede
 *        - 'sede_cerrada'       → respuesta específica
 *        - 'no_aplica'          → no hay dirección o no es despacho
 *
 * Beneficios:
 *   - No depende del LLM (que a veces dice "voy a validar" pero no lo hace).
 *   - Determinista: misma entrada = misma salida.
 *   - Sin tokens consumidos.
 *   - Resuelve UN problema, lo resuelve bien.
 */
class AgenteCoberturaService
{
    /**
     * Evalúa el estado y, si aplica, ejecuta la validación de cobertura.
     *
     * @return array{
     *   accion: 'cubierta'|'fuera_de_cobertura'|'sede_cerrada'|'no_aplica',
     *   reply: ?string,
     *   resultado: ?array,
     * }
     */
    public function evaluar(ConversacionWhatsapp $conv, ?int $connectionId = null): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);

        // Solo aplica para despacho con dirección, sin cobertura validada
        if ($estado->metodo_entrega !== ConversacionPedidoEstado::METODO_DOMICILIO) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }
        if (empty($estado->direccion)) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }
        if ($estado->cobertura_validada) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }

        $direccion = (string) $estado->direccion;
        $barrio    = (string) ($estado->barrio ?? '');
        $ciudad    = (string) ($estado->ciudad ?? 'Bello');

        Log::info('🗺️ AgenteCoberturaService: validando cobertura sin LLM', [
            'conv_id'  => $conv->id,
            'direccion'=> $direccion,
            'ciudad'   => $ciudad,
        ]);

        // Resolver sede actual y ejecutar validación reutilizando lógica del controller
        $sedeId = $this->resolverSedeId($connectionId);

        try {
            $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
            $reflexion = new \ReflectionMethod($controller, 'validarCoberturaDireccion');
            $reflexion->setAccessible(true);
            $resultado = $reflexion->invoke(
                $controller,
                $direccion,
                $barrio,
                $ciudad,
                $sedeId,
                $conv->telefono_normalizado
            );
        } catch (\Throwable $e) {
            Log::warning('🗺️ AgenteCoberturaService: falló validación: ' . $e->getMessage());
            return [
                'accion'    => 'no_aplica',
                'reply'     => null,
                'resultado' => null,
            ];
        }

        // Persistir resultado
        try {
            app(EstadoPedidoService::class)->captarCobertura($conv, $resultado);
        } catch (\Throwable $e) {
            Log::warning('🗺️ AgenteCoberturaService: no pudo persistir cobertura: ' . $e->getMessage());
        }

        $cubierta = (bool) ($resultado['cubierta'] ?? false);

        if ($cubierta) {
            Log::info('✅ AgenteCoberturaService: cobertura OK', [
                'conv_id'      => $conv->id,
                'sede'         => $resultado['sede_sugerida'] ?? null,
                'distancia_km' => $resultado['distancia_km'] ?? null,
            ]);
            return [
                'accion'    => 'cubierta',
                'reply'     => null, // continúa flujo, no envía mensaje
                'resultado' => $resultado,
            ];
        }

        // Fuera de cobertura → respuesta determinista
        $sedesAbiertas = $this->sedesAbiertasResumen();
        $reply = $this->construirRespuestaFueraCobertura($direccion, $sedesAbiertas);

        Log::warning('🚫 AgenteCoberturaService: dirección fuera de cobertura', [
            'conv_id'  => $conv->id,
            'direccion'=> $direccion,
            'mensaje'  => $resultado['mensaje_sugerido'] ?? null,
        ]);

        return [
            'accion'    => 'fuera_de_cobertura',
            'reply'     => $reply,
            'resultado' => $resultado,
        ];
    }

    private function resolverSedeId(?int $connectionId): ?int
    {
        if ($connectionId) {
            try {
                $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
                $rm = new \ReflectionMethod($controller, 'obtenerSedeIdDesdeConexion');
                $rm->setAccessible(true);
                $id = $rm->invoke($controller, $connectionId);
                if ($id) return $id;
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $primera = Sede::where('activa', true)->first();
        return $primera?->id;
    }

    private function sedesAbiertasResumen(): array
    {
        return Sede::where('activa', true)->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'nombre'     => $s->nombre,
                'direccion'  => $s->direccion ?? null,
                'abierta'    => $s->estaAbierta(),
            ])
            ->all();
    }

    private function construirRespuestaFueraCobertura(string $direccion, array $sedes): string
    {
        $abiertas = collect($sedes)->where('abierta', true)->values();
        $hayAbiertas = $abiertas->isNotEmpty();

        $msg = "Lo siento 🙏, la dirección *{$direccion}* está fuera de nuestra zona de cobertura para domicilio.\n\n";

        if ($hayAbiertas) {
            $msg .= "*¿Qué te ofrezco?*\n";
            $msg .= "1. *Recoger en sede* — tenemos disponible:\n";
            foreach ($abiertas->take(3) as $s) {
                $dir = $s['direccion'] ? " · {$s['direccion']}" : '';
                $msg .= "   • *{$s['nombre']}*{$dir}\n";
            }
            $msg .= "2. *Otra dirección* dentro de nuestra zona — pásame y la valido.\n";
        } else {
            $msg .= "Por ahora todas nuestras sedes están cerradas. Cuando vuelvan a abrir podremos coordinar tu pedido.";
        }

        return $msg;
    }
}
