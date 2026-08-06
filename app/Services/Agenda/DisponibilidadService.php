<?php

namespace App\Services\Agenda;

use App\Models\AgendaConfiguracion;
use App\Models\Cita;
use Carbon\Carbon;

/**
 * Calcula los horarios LIBRES de un día según la configuración de agenda del
 * tenant y las citas ya reservadas. (Fase 2: además cruzará con Google FreeBusy.)
 */
class DisponibilidadService
{
    /**
     * @return array<int,Carbon> horas de inicio disponibles ese día
     */
    public function slotsLibres(AgendaConfiguracion $cfg, Carbon $fecha): array
    {
        $tz = $cfg->zona_horaria ?: 'America/Bogota';
        $fecha = $fecha->copy()->setTimezone($tz)->startOfDay();

        // ¿Día hábil?
        $dias = $cfg->dias ?: [1, 2, 3, 4, 5];
        if (!in_array((int) $fecha->isoWeekday(), array_map('intval', $dias), true)) {
            return [];
        }

        $duracion = max(5, (int) $cfg->duracion_min);
        $paso     = $duracion + max(0, (int) $cfg->buffer_min);

        [$hi, $mi] = array_pad(explode(':', (string) $cfg->hora_inicio), 2, 0);
        [$hf, $mf] = array_pad(explode(':', (string) $cfg->hora_fin), 2, 0);
        $inicio = $fecha->copy()->setTime((int) $hi, (int) $mi);
        $fin    = $fecha->copy()->setTime((int) $hf, (int) $mf);

        // Citas activas de ese día (para excluir solapes)
        $ocupadas = Cita::activas()
            ->whereBetween('inicio_at', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])
            ->get(['inicio_at', 'fin_at']);

        $ahora  = Carbon::now($tz);
        $slots  = [];
        $cursor = $inicio->copy();

        while ($cursor->copy()->addMinutes($duracion)->lte($fin)) {
            $slotFin = $cursor->copy()->addMinutes($duracion);

            $enPasado = $cursor->lte($ahora);
            $solapa = $ocupadas->contains(function ($c) use ($cursor, $slotFin) {
                return $cursor->lt($c->fin_at) && $slotFin->gt($c->inicio_at);
            });

            if (!$enPasado && !$solapa) {
                $slots[] = $cursor->copy();
            }
            $cursor->addMinutes($paso);
        }

        return $slots;
    }
}
