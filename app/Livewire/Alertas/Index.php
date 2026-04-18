<?php

namespace App\Livewire\Alertas;

use App\Models\BotAlerta;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filtroEstado    = 'no_resueltas'; // todas | no_resueltas | resueltas
    public string $filtroSeveridad = 'todas';        // todas | critica | warning | info
    public string $filtroTipo      = 'todas';        // todas | (cualquier tipo)
    public string $busqueda        = '';

    public ?int $detalleId = null;

    protected $queryString = ['filtroEstado', 'filtroSeveridad', 'filtroTipo', 'busqueda'];

    public function updating($name): void
    {
        if (in_array($name, ['filtroEstado', 'filtroSeveridad', 'filtroTipo', 'busqueda'], true)) {
            $this->resetPage();
        }
    }

    public function abrirDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function resolver(int $id): void
    {
        $a = BotAlerta::find($id);
        if (!$a) return;

        $a->update([
            'resuelta'     => true,
            'resuelta_at'  => now(),
            'resuelta_por' => auth()->user()?->name ?? 'sistema',
        ]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Alerta marcada como resuelta',
        ]);
    }

    public function reabrir(int $id): void
    {
        BotAlerta::where('id', $id)->update([
            'resuelta'     => false,
            'resuelta_at'  => null,
            'resuelta_por' => null,
        ]);

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Alerta reabierta',
        ]);
    }

    public function eliminar(int $id): void
    {
        BotAlerta::where('id', $id)->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Alerta eliminada',
        ]);

        if ($this->detalleId === $id) {
            $this->detalleId = null;
        }
    }

    public function resolverTodas(): void
    {
        $count = BotAlerta::where('resuelta', false)->update([
            'resuelta'     => true,
            'resuelta_at'  => now(),
            'resuelta_por' => auth()->user()?->name ?? 'sistema',
        ]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✓ {$count} alertas marcadas como resueltas",
        ]);
    }

    public function limpiarResueltas(): void
    {
        $count = BotAlerta::where('resuelta', true)->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "🗑️ {$count} alertas resueltas eliminadas",
        ]);
    }

    public function render()
    {
        $query = BotAlerta::query();

        if ($this->filtroEstado === 'no_resueltas') {
            $query->where('resuelta', false);
        } elseif ($this->filtroEstado === 'resueltas') {
            $query->where('resuelta', true);
        }

        if ($this->filtroSeveridad !== 'todas') {
            $query->where('severidad', $this->filtroSeveridad);
        }

        if ($this->filtroTipo !== 'todas') {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('titulo', 'like', "%{$b}%")
                  ->orWhere('mensaje', 'like', "%{$b}%");
            });
        }

        $alertas = $query
            ->orderByDesc('resuelta')   // no resueltas primero (false=0 < true=1) → invertimos
            ->orderByRaw('CASE WHEN resuelta = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('ultima_ocurrencia_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $totales = [
            'total'        => BotAlerta::count(),
            'no_resueltas' => BotAlerta::where('resuelta', false)->count(),
            'criticas'     => BotAlerta::where('resuelta', false)->where('severidad', BotAlerta::SEV_CRITICA)->count(),
            'warnings'     => BotAlerta::where('resuelta', false)->where('severidad', BotAlerta::SEV_WARNING)->count(),
        ];

        $tipos = [
            'todas'                          => 'Todos los tipos',
            BotAlerta::TIPO_OPENAI_CREDITO   => '💸 OpenAI sin créditos',
            BotAlerta::TIPO_OPENAI_KEY       => '🔑 OpenAI API key',
            BotAlerta::TIPO_OPENAI_RATE      => '⏱️ OpenAI rate limit',
            BotAlerta::TIPO_OPENAI_MODELO    => '🧠 OpenAI modelo',
            BotAlerta::TIPO_OPENAI_TIMEOUT   => '⌛ OpenAI timeout',
            BotAlerta::TIPO_OPENAI_OTRO      => '🤖 OpenAI otro',
            BotAlerta::TIPO_WHATSAPP_TOKEN   => '📱 WhatsApp token',
            BotAlerta::TIPO_WHATSAPP_ENVIO   => '📤 WhatsApp envío',
            BotAlerta::TIPO_REVERB           => '🔌 Reverb',
            BotAlerta::TIPO_OTRO             => '⚠️ Otro',
        ];

        $detalle = $this->detalleId ? BotAlerta::find($this->detalleId) : null;

        return view('livewire.alertas.index', compact('alertas', 'totales', 'tipos', 'detalle'))
            ->layout('layouts.app');
    }
}
