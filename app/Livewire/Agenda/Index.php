<?php

namespace App\Livewire\Agenda;

use App\Models\AgendaConfiguracion;
use App\Models\Cita;
use App\Services\Agenda\DisponibilidadService;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Panel de AGENDA del tenant: configurar horarios y crear/ver citas.
 * (La sincronización con Google Calendar se conecta en la fase 2.)
 */
class Index extends Component
{
    // ── Configuración ──────────────────────────────────────
    public int    $duracion_min = 60;
    public int    $buffer_min   = 0;
    public array  $dias         = [1, 2, 3, 4, 5];
    public string $hora_inicio  = '08:00';
    public string $hora_fin     = '18:00';
    public bool   $activa       = true;
    public bool   $mostrarConfig = false;

    // ── Nueva cita ─────────────────────────────────────────
    public string $nc_fecha    = '';
    public ?string $nc_slot    = null;   // ISO datetime del slot elegido
    public string $nc_nombre   = '';
    public string $nc_telefono = '';
    public string $nc_motivo   = '';

    public function mount(): void
    {
        $cfg = AgendaConfiguracion::paraTenantActual();
        $this->duracion_min = (int) $cfg->duracion_min;
        $this->buffer_min   = (int) $cfg->buffer_min;
        $this->dias         = $cfg->dias ?: [1, 2, 3, 4, 5];
        $this->hora_inicio  = substr((string) $cfg->hora_inicio, 0, 5);
        $this->hora_fin     = substr((string) $cfg->hora_fin, 0, 5);
        $this->activa       = (bool) $cfg->activa;
        $this->nc_fecha     = Carbon::now($cfg->zona_horaria ?: 'America/Bogota')->format('Y-m-d');
    }

    public function guardarConfig(): void
    {
        $this->validate([
            'duracion_min' => 'required|integer|min:5|max:600',
            'buffer_min'   => 'integer|min:0|max:240',
            'hora_inicio'  => 'required|date_format:H:i',
            'hora_fin'     => 'required|date_format:H:i|after:hora_inicio',
            'dias'         => 'array',
        ]);

        AgendaConfiguracion::paraTenantActual()->update([
            'duracion_min' => $this->duracion_min,
            'buffer_min'   => $this->buffer_min,
            'dias'         => array_values(array_map('intval', $this->dias)),
            'hora_inicio'  => $this->hora_inicio,
            'hora_fin'     => $this->hora_fin,
            'activa'       => $this->activa,
        ]);

        $this->nc_slot = null; // los slots pueden haber cambiado
        $this->mostrarConfig = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Configuración de agenda guardada.']);
    }

    public function toggleDia(int $d): void
    {
        if (in_array($d, $this->dias, true)) {
            $this->dias = array_values(array_diff($this->dias, [$d]));
        } else {
            $this->dias[] = $d;
            sort($this->dias);
        }
    }

    public function seleccionarSlot(string $iso): void
    {
        $this->nc_slot = $iso;
    }

    public function updatedNcFecha(): void
    {
        $this->nc_slot = null;
    }

    public function guardarCita(): void
    {
        $this->validate([
            'nc_slot'    => 'required',
            'nc_nombre'  => 'required|string|max:150',
            'nc_telefono' => 'nullable|string|max:30',
            'nc_motivo'  => 'nullable|string|max:200',
        ], [], ['nc_slot' => 'horario', 'nc_nombre' => 'nombre del paciente']);

        $cfg    = AgendaConfiguracion::paraTenantActual();
        $inicio = Carbon::parse($this->nc_slot, $cfg->zona_horaria ?: 'America/Bogota');
        $fin    = $inicio->copy()->addMinutes((int) $cfg->duracion_min);

        // Revalidar que siga libre (evita doble reserva)
        $choca = Cita::activas()
            ->where('inicio_at', '<', $fin)
            ->where('fin_at', '>', $inicio)
            ->exists();
        if ($choca) {
            $this->nc_slot = null;
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Ese horario acaba de ocuparse. Elige otro.']);
            return;
        }

        Cita::create([
            'paciente_nombre'   => trim($this->nc_nombre),
            'paciente_telefono' => trim($this->nc_telefono) ?: null,
            'inicio_at'         => $inicio,
            'fin_at'            => $fin,
            'estado'            => 'confirmada',
            'motivo'            => trim($this->nc_motivo) ?: null,
            'origen'            => 'panel',
            'creado_por'        => auth()->id(),
        ]);

        // TODO fase 2: crear evento en Google Calendar (si google_conectado).

        $this->reset(['nc_slot', 'nc_nombre', 'nc_telefono', 'nc_motivo']);
        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Cita agendada.']);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, Cita::ESTADOS, true)) return;
        $c = Cita::find($id);
        if ($c) { $c->estado = $estado; $c->save(); }
    }

    public function render()
    {
        $cfg = AgendaConfiguracion::paraTenantActual();
        $tz  = $cfg->zona_horaria ?: 'America/Bogota';

        $slots = [];
        if ($this->nc_fecha) {
            try {
                $slots = app(DisponibilidadService::class)->slotsLibres($cfg, Carbon::parse($this->nc_fecha, $tz));
            } catch (\Throwable $e) { $slots = []; }
        }

        $proximas = Cita::activas()
            ->where('inicio_at', '>=', Carbon::now($tz)->startOfDay())
            ->orderBy('inicio_at')
            ->limit(60)
            ->get();

        return view('livewire.agenda.index', [
            'cfg'      => $cfg,
            'slots'    => $slots,
            'proximas' => $proximas,
        ])->layout('layouts.app');
    }
}
