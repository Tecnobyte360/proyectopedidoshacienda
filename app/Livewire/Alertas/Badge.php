<?php

namespace App\Livewire\Alertas;

use App\Models\BotAlerta;
use Livewire\Attributes\On;
use Livewire\Component;

class Badge extends Component
{
    public bool $abierto = false;

    public function toggle(): void
    {
        $this->abierto = !$this->abierto;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    #[On('alerta-registrada')]
    public function refrescar(): void
    {
        // re-render
    }

    public function marcarVistas(): void
    {
        try {
            if (\Schema::hasTable('bot_alertas')) {
                BotAlerta::whereNull('vista_at')->update(['vista_at' => now()]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function render()
    {
        $noResueltas = 0;
        $criticas    = 0;
        $ultimas     = collect();

        try {
            if (\Schema::hasTable('bot_alertas')) {
                $noResueltas = BotAlerta::where('resuelta', false)->count();
                $criticas    = BotAlerta::where('resuelta', false)
                    ->where('severidad', BotAlerta::SEV_CRITICA)
                    ->count();
                $ultimas = BotAlerta::recientes()->limit(8)->get();
            }
        } catch (\Throwable $e) {
            // Si algo falla (BD caída, tabla pendiente de migrar, etc.), no rompemos toda la UI
            \Log::warning('Badge alertas: ' . $e->getMessage());
        }

        return view('livewire.alertas.badge', [
            'noResueltas' => $noResueltas,
            'criticas'    => $criticas,
            'ultimas'     => $ultimas,
        ]);
    }
}
