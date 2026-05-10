<?php

namespace App\Livewire\Bot;

use App\Models\BotAlerta;
use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\Pedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * 📡 MONITOR EN VIVO DEL BOT
 *
 * Dashboard auto-refrescante (cada 3s) que muestra:
 *  - KPIs del día (pedidos creados, conversaciones activas, alucinaciones detectadas)
 *  - Conversaciones en curso con su paso del orquestador
 *  - Timeline de últimos eventos del bot (alucinación, BotCierre, pedido creado)
 *  - Tasa de éxito en tiempo real
 */
class Monitor extends Component
{
    public int $ventanaMinutos = 30; // mostrar conversaciones de últimos N minutos
    public ?int $conversacionFocoId = null; // si se hace foco en una

    public function abrirFoco(int $convId): void
    {
        $this->conversacionFocoId = $convId;
    }

    public function cerrarFoco(): void
    {
        $this->conversacionFocoId = null;
    }

    public function getEstadosActivosProperty()
    {
        $desde = now()->subMinutes($this->ventanaMinutos);

        return ConversacionPedidoEstado::query()
            ->with(['conversacion.cliente', 'sede', 'pedido'])
            ->where('updated_at', '>=', $desde)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();
    }

    public function getKpisHoyProperty(): array
    {
        $hoy = now()->startOfDay();

        $pedidosHoy = Pedido::whereDate('created_at', today())->count();
        $totalPedidos = Pedido::whereDate('created_at', today())->sum('total');

        $alucinacionesHoy = BotAlerta::whereDate('created_at', today())
            ->where('titulo', 'like', '%dijo que confirmó%')
            ->count();

        $alertasHoy = BotAlerta::whereDate('created_at', today())->count();

        $convActivas = ConversacionWhatsapp::query()
            ->where('ultimo_mensaje_at', '>=', now()->subMinutes(60))
            ->count();

        $estadosActivos = ConversacionPedidoEstado::query()
            ->where('updated_at', '>=', now()->subMinutes(60))
            ->whereNotIn('paso_actual', [
                ConversacionPedidoEstado::PASO_CONFIRMADO,
                ConversacionPedidoEstado::PASO_ABANDONADO,
            ])
            ->count();

        // Conversaciones que necesitan humano (handoff pendiente)
        $requierenHumano = ConversacionWhatsapp::query()
            ->where('requiere_humano', true)
            ->where('ultimo_mensaje_at', '>=', now()->subHours(4))
            ->count();

        // Tasa de éxito últimas 24h: pedidos confirmados / conversaciones iniciadas
        $convsUltDia = ConversacionWhatsapp::where('created_at', '>=', now()->subDay())->count();
        $pedidosUltDia = Pedido::where('created_at', '>=', now()->subDay())->count();
        $tasaExito = $convsUltDia > 0 ? round(($pedidosUltDia / $convsUltDia) * 100, 1) : 0;

        // Proveedor IA actual + provider activo
        $cfg = \App\Models\ConfiguracionBot::actual();
        $proveedorActivo = $cfg?->ai_provider ?: 'openai';
        $modeloActivo = $proveedorActivo === 'anthropic'
            ? ($cfg?->modelo_anthropic ?: 'claude-sonnet-4-6')
            : ($cfg?->modelo_openai ?: 'gpt-4o-mini');

        // Tiempo promedio de cierre (mensajes hasta confirmar pedido)
        $promedioMensajes = ConversacionPedidoEstado::query()
            ->where('confirmado_at', '>=', now()->subDay())
            ->whereNotNull('confirmado_at')
            ->get()
            ->map(function ($e) {
                return MensajeWhatsapp::where('conversacion_id', $e->conversacion_id)
                    ->where('created_at', '<=', $e->confirmado_at)
                    ->where('rol', 'user')
                    ->count();
            })
            ->avg();
        $promedioMensajes = $promedioMensajes ? round($promedioMensajes, 1) : null;

        return [
            'pedidos_hoy'        => $pedidosHoy,
            'total_facturado'    => $totalPedidos,
            'alucinaciones'      => $alucinacionesHoy,
            'alertas_total'      => $alertasHoy,
            'conv_activas_60m'   => $convActivas,
            'estados_en_curso'   => $estadosActivos,
            'requieren_humano'   => $requierenHumano,
            'tasa_exito_24h'     => $tasaExito,
            'proveedor_ia'       => $proveedorActivo,
            'modelo_ia'          => $modeloActivo,
            'promedio_mensajes'  => $promedioMensajes,
        ];
    }

    /**
     * Conversaciones que requieren atención humana — listado priorizado.
     */
    public function getConversacionesHumanoProperty()
    {
        return ConversacionWhatsapp::with(['cliente'])
            ->where('requiere_humano', true)
            ->where('ultimo_mensaje_at', '>=', now()->subHours(4))
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(10)
            ->get();
    }

    /**
     * Lee los últimos eventos de log del bot (vía bot_alertas + agente_tool_invocaciones).
     * Combina ambos y los presenta como timeline cronológico.
     */
    public function getTimelineEventosProperty()
    {
        $desde = now()->subMinutes($this->ventanaMinutos);

        // Alertas del bot (alucinaciones, errores OpenAI, etc.)
        $alertas = BotAlerta::where('created_at', '>=', $desde)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'tipo', 'titulo', 'severidad', 'created_at', 'contexto'])
            ->map(fn ($a) => [
                'tipo'      => 'alerta',
                'subtipo'   => $a->tipo,
                'titulo'    => $a->titulo,
                'severidad' => $a->severidad,
                'meta'      => $a->contexto,
                'icon'      => '⚠️',
                'color'     => 'amber',
                'at'        => $a->created_at,
            ])
            ->toBase(); // Eloquent → Support Collection (evita errores de getKey en merge)

        // Tool invocaciones recientes
        $toolInvocaciones = collect();
        try {
            $toolInvocaciones = DB::table('agente_tool_invocaciones')
                ->where('created_at', '>=', $desde)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'tool_name', 'telefono_cliente', 'created_at', 'exitoso', 'count_resultados'])
                ->map(fn ($t) => [
                    'tipo'    => 'tool',
                    'subtipo' => $t->tool_name,
                    'titulo'  => "🛠️ {$t->tool_name} ({$t->count_resultados} resultados)",
                    'meta'    => ['from' => $t->telefono_cliente, 'exitoso' => $t->exitoso],
                    'icon'    => $t->exitoso ? '🛠️' : '❌',
                    'color'   => $t->exitoso ? 'cyan' : 'rose',
                    'at'      => Carbon::parse($t->created_at),
                ]);
        } catch (\Throwable $e) {
            // tabla puede no existir en algunos tenants
        }

        // Pedidos creados recientes
        $pedidosNuevos = Pedido::where('created_at', '>=', $desde)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'cliente_nombre', 'total', 'created_at'])
            ->map(fn ($p) => [
                'tipo'   => 'pedido',
                'titulo' => "✅ Pedido #{$p->id} de {$p->cliente_nombre} (\$" . number_format($p->total, 0, ',', '.') . ")",
                'icon'   => '✅',
                'color'  => 'emerald',
                'at'     => $p->created_at,
                'meta'   => ['pedido_id' => $p->id],
            ])
            ->toBase(); // Eloquent → Support Collection

        return $alertas
            ->merge($toolInvocaciones)
            ->merge($pedidosNuevos)
            ->sortByDesc('at')
            ->take(30)
            ->values();
    }

    public function getConversacionFocoProperty()
    {
        if (!$this->conversacionFocoId) return null;
        return ConversacionWhatsapp::with(['cliente', 'mensajes' => function ($q) {
            $q->latest('id')->limit(15);
        }])->find($this->conversacionFocoId);
    }

    public function getEstadoFocoProperty()
    {
        if (!$this->conversacionFocoId) return null;
        return ConversacionPedidoEstado::with('sede', 'pedido')
            ->where('conversacion_id', $this->conversacionFocoId)
            ->first();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.bot.monitor');
    }
}
