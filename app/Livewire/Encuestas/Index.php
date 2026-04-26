<?php

namespace App\Livewire\Encuestas;

use App\Models\EncuestaPedido;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filtro = 'todas';   // todas | completadas | pendientes | bajas | sin_enviar | enviadas_no_respondidas
    public string $busqueda = '';
    public ?int $domiciliarioId = null;

    protected $paginationTheme = 'tailwind';

    public function updating($name): void { $this->resetPage(); }

    public function getEstadisticasProperty(): array
    {
        $base = EncuestaPedido::query();

        $total       = (clone $base)->count();
        $sinEnviar   = (clone $base)->whereNull('enviada_at')->count();
        $enviadas    = (clone $base)->whereNotNull('enviada_at')->count();
        $vistas      = (clone $base)->whereNotNull('vista_at')->count();
        $completadas = (clone $base)->whereNotNull('completada_at')->count();
        $promProc    = (clone $base)->whereNotNull('calificacion_proceso')->avg('calificacion_proceso');
        $promDom     = (clone $base)->whereNotNull('calificacion_domiciliario')->avg('calificacion_domiciliario');
        $recom       = (clone $base)->where('recomendaria', true)->count();
        $noRecom     = (clone $base)->where('recomendaria', false)->count();

        return [
            'total'       => $total,
            'sin_enviar'  => $sinEnviar,
            'enviadas'    => $enviadas,
            'vistas'      => $vistas,
            'completadas' => $completadas,
            'pendientes'  => $total - $completadas,
            'tasa'        => $total > 0 ? round($completadas / $total * 100, 1) : 0,
            'prom_proc'   => round((float) $promProc, 1),
            'prom_dom'    => round((float) $promDom, 1),
            'recom_si'    => $recom,
            'recom_no'    => $noRecom,
        ];
    }

    /**
     * Reenvía manualmente una encuesta (útil cuando el queue worker estaba caído
     * o el cliente nunca recibió el mensaje).
     */
    public function reenviarEncuesta(int $encuestaId): void
    {
        $encuesta = EncuestaPedido::with('pedido')->find($encuestaId);
        if (!$encuesta) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Encuesta no encontrada.']);
            return;
        }

        $pedido = $encuesta->pedido;
        if (!$pedido) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Pedido asociado no encontrado.']);
            return;
        }

        $telefono = $pedido->telefono_whatsapp ?: $pedido->telefono_contacto ?: $pedido->telefono;
        if (!$telefono) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El pedido no tiene teléfono.']);
            return;
        }

        // Construir el mensaje igual que en Pedido::programarEncuestaEntrega
        $cfg = \App\Models\ConfiguracionBot::actual();
        $primerNombre = trim(explode(' ', (string) $pedido->cliente_nombre)[0] ?: 'cliente');
        $domiciliario = $pedido->domiciliario_id ? \App\Models\Domiciliario::find($pedido->domiciliario_id) : null;
        $nombreDom = $domiciliario?->nombre ?? 'el domiciliario';

        $plantilla = trim((string) ($cfg->encuesta_mensaje ?? '')) ?:
            "{nombre}, esperamos que hayas disfrutado tu pedido 🍽️\n\n¿Nos cuentas cómo estuvo todo? 🙏\n\n👉 {url}";

        $mensaje = strtr($plantilla, [
            '{nombre}'          => $primerNombre,
            '{nombre_completo}' => $pedido->cliente_nombre ?: '',
            '{domiciliario}'    => $nombreDom,
            '{url}'             => $encuesta->urlPublica(),
            '{pedido}'          => '#' . $pedido->id,
        ]);

        // Ejecutar AHORA (sin queue) para que el admin sepa el resultado
        try {
            $job = new \App\Jobs\EnviarEncuestaEntrega($encuesta->id, $telefono, $mensaje);
            $job->handle(app(\App\Services\WhatsappSenderService::class));

            $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Encuesta enviada a ' . $primerNombre]);
        } catch (\Throwable $e) {
            \Log::error('Reenvio encuesta fallo: ' . $e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        $q = EncuestaPedido::with(['pedido:id,cliente_nombre,total,telefono_whatsapp', 'domiciliario:id,nombre'])
            ->when($this->filtro === 'completadas', fn ($qq) => $qq->whereNotNull('completada_at'))
            ->when($this->filtro === 'pendientes',  fn ($qq) => $qq->whereNull('completada_at'))
            ->when($this->filtro === 'sin_enviar',  fn ($qq) => $qq->whereNull('enviada_at'))
            ->when($this->filtro === 'enviadas_no_respondidas', fn ($qq) => $qq->whereNotNull('enviada_at')->whereNull('completada_at'))
            ->when($this->filtro === 'bajas',       fn ($qq) => $qq->where('calificacion_proceso', '<=', 3))
            ->when($this->domiciliarioId, fn ($qq) => $qq->where('domiciliario_id', $this->domiciliarioId))
            ->when($this->busqueda, function ($qq) {
                $qq->whereHas('pedido', fn ($p) => $p->where('cliente_nombre', 'like', "%{$this->busqueda}%"));
            })
            ->orderByDesc('id');

        return view('livewire.encuestas.index', [
            'encuestas'      => $q->paginate(20),
            'estadisticas'   => $this->estadisticas,
            'domiciliarios'  => \App\Models\Domiciliario::orderBy('nombre')->get(['id','nombre']),
        ])->layout('layouts.app');
    }
}
