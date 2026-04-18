<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\DB;

/**
 * Maneja la persistencia de conversaciones WhatsApp en BD.
 *
 * Convención: una conversación "activa" por cliente. Si pasa mucho tiempo
 * (configurable), se cierra la actual y se abre una nueva. Esto evita que
 * UN cliente acumule cientos de mensajes en una sola conversación.
 */
class ConversacionService
{
    /** Minutos sin actividad para considerar una conversación "cerrada" */
    private const MINUTOS_INACTIVIDAD_PARA_CERRAR = 60 * 6;   // 6 horas

    /**
     * Encuentra la conversación activa del cliente o crea una nueva.
     */
    public function obtenerOCrearActiva(
        string $telefonoNormalizado,
        ?int $clienteId = null,
        ?int $sedeId = null,
        ?int $connectionId = null
    ): ConversacionWhatsapp {
        // Buscar la última activa
        $activa = ConversacionWhatsapp::where('telefono_normalizado', $telefonoNormalizado)
            ->where('estado', ConversacionWhatsapp::ESTADO_ACTIVA)
            ->orderByDesc('id')
            ->first();

        if ($activa) {
            // Si lleva mucho sin mensajes, la cerramos y abrimos nueva
            if (
                $activa->ultimo_mensaje_at &&
                $activa->ultimo_mensaje_at->diffInMinutes(now()) > self::MINUTOS_INACTIVIDAD_PARA_CERRAR
            ) {
                $activa->update(['estado' => ConversacionWhatsapp::ESTADO_CERRADA]);
                return $this->crearNueva($telefonoNormalizado, $clienteId, $sedeId, $connectionId);
            }

            // Actualizar cliente_id si llegó después
            if ($clienteId && !$activa->cliente_id) {
                $activa->update(['cliente_id' => $clienteId]);
            }

            return $activa;
        }

        return $this->crearNueva($telefonoNormalizado, $clienteId, $sedeId, $connectionId);
    }

    private function crearNueva(
        string $telefonoNormalizado,
        ?int $clienteId,
        ?int $sedeId,
        ?int $connectionId
    ): ConversacionWhatsapp {
        return ConversacionWhatsapp::create([
            'telefono_normalizado' => $telefonoNormalizado,
            'cliente_id'           => $clienteId,
            'sede_id'              => $sedeId,
            'connection_id'        => $connectionId,
            'estado'               => ConversacionWhatsapp::ESTADO_ACTIVA,
            'primer_mensaje_at'    => now(),
            'ultimo_mensaje_at'    => now(),
        ]);
    }

    /**
     * Registra un mensaje en la conversación y actualiza contadores.
     */
    public function agregarMensaje(
        ConversacionWhatsapp $conversacion,
        string $rol,
        ?string $contenido,
        array $opciones = []
    ): MensajeWhatsapp {
        return DB::transaction(function () use ($conversacion, $rol, $contenido, $opciones) {
            $mensaje = MensajeWhatsapp::create([
                'conversacion_id'    => $conversacion->id,
                'rol'                => $rol,
                'tipo'               => $opciones['tipo']               ?? 'text',
                'contenido'          => $contenido,
                'meta'               => $opciones['meta']               ?? null,
                'mensaje_externo_id' => $opciones['mensaje_externo_id'] ?? null,
                'latencia_ms'        => $opciones['latencia_ms']        ?? null,
                'tokens_input'       => $opciones['tokens_input']       ?? null,
                'tokens_output'      => $opciones['tokens_output']      ?? null,
            ]);

            // Actualizar contadores
            $updates = [
                'ultimo_mensaje_at' => now(),
                'total_mensajes'    => $conversacion->total_mensajes + 1,
            ];

            if ($rol === MensajeWhatsapp::ROL_USER) {
                $updates['total_mensajes_cliente'] = $conversacion->total_mensajes_cliente + 1;
            } elseif ($rol === MensajeWhatsapp::ROL_ASSISTANT) {
                $updates['total_mensajes_bot'] = $conversacion->total_mensajes_bot + 1;
            }

            $conversacion->update($updates);

            return $mensaje;
        });
    }

    /**
     * Marca la conversación como ganadora de un pedido.
     */
    public function vincularPedido(ConversacionWhatsapp $conversacion, int $pedidoId): void
    {
        $conversacion->update([
            'genero_pedido' => true,
            'pedido_id'     => $pedidoId,
        ]);
    }

    /**
     * Cierra la conversación (típicamente cuando se confirma pedido).
     */
    public function cerrar(ConversacionWhatsapp $conversacion): void
    {
        $conversacion->update(['estado' => ConversacionWhatsapp::ESTADO_CERRADA]);
    }
}
