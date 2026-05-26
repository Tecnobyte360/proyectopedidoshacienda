<?php

namespace App\Livewire\Ivr;

use App\Models\LlamadaIvr;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Monitor extends Component
{
    use WithPagination;

    public string $rango  = 'hoy';     // hoy | semana | mes
    public string $estado = '';        // '' | en_curso | terminada_ok | voicemail | etc.
    public string $busqueda = '';

    public function render()
    {
        $desde = match($this->rango) {
            'semana' => now()->subDays(7),
            'mes'    => now()->subDays(30),
            default  => now()->startOfDay(),
        };

        $base = LlamadaIvr::query()
            ->with(['cliente', 'pedido'])
            ->where('iniciada_at', '>=', $desde)
            ->when($this->estado,   fn($q) => $q->where('estado', $this->estado))
            ->when($this->busqueda, fn($q) => $q->where(function ($qq) {
                $qq->where('caller_id', 'like', "%{$this->busqueda}%")
                   ->orWhereHas('cliente', fn($c) => $c->where('nombre', 'like', "%{$this->busqueda}%"));
            }))
            ->orderByDesc('iniciada_at');

        $llamadas = (clone $base)->paginate(20);

        // KPIs
        $kpis = [
            'total'       => (clone $base)->count(),
            'transferidas'=> (clone $base)->where('transferida', true)->count(),
            'voicemails'  => (clone $base)->where('dejo_voicemail', true)->count(),
            'consultas_pedido' => (clone $base)->whereNotNull('pedido_consultado_id')->count(),
            'duracion_promedio'=> (int) (clone $base)->whereNotNull('duracion_segundos')->avg('duracion_segundos'),
        ];

        // Opciones más elegidas (query nueva sin el orderBy de la base)
        $opciones = LlamadaIvr::query()
            ->where('iniciada_at', '>=', $desde)
            ->whereNotNull('opcion_elegida')
            ->selectRaw('opcion_elegida, count(*) as total')
            ->groupBy('opcion_elegida')
            ->orderByDesc('total')
            ->get();

        return view('livewire.ivr.monitor', compact('llamadas', 'kpis', 'opciones'));
    }
}
