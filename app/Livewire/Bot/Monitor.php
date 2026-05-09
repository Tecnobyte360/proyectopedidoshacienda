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

        return [
            'pedidos_hoy'      => $pedidosHoy,
            'total_facturado'  => $totalPedidos,
            'alucinaciones'    => $alucinacionesHoy,
            'alertas_total'    => $alertasHoy,
            'conv_activas_60m' => $convActivas,
            'estados_en_curso' => $estadosActivos,
        ];
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
            ]);

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
            ]);

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
