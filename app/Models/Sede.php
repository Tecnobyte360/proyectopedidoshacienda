<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'nombre',
        'direccion',
        'latitud',
        'longitud',
        'hora_apertura',
        'hora_cierre',
        'horarios',
        'mensaje_cerrado',
        'activa',
    ];

    protected $casts = [
        'latitud'  => 'float',
        'longitud' => 'float',
        'activa'   => 'boolean',
        'horarios' => 'array',
    ];

    public const DIAS_SEMANA = [
        'lunes'     => 'Lunes',
        'martes'    => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves'    => 'Jueves',
        'viernes'   => 'Viernes',
        'sabado'    => 'Sábado',
        'domingo'   => 'Domingo',
    ];

    /** Mapeo Carbon dayOfWeek (0=Dom..6=Sab) → clave del array horarios */
    private const CARBON_A_DIA = [
        0 => 'domingo',
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
    ];

    /**
     * Devuelve los horarios estructurados (con valores por defecto si está vacío).
     */
    public function horariosCompletos(): array
    {
        $base = $this->horarios ?? [];
        $resultado = [];

        foreach (self::DIAS_SEMANA as $key => $label) {
            $resultado[$key] = [
                'abierto' => $base[$key]['abierto'] ?? true,
                'abre'    => $base[$key]['abre']    ?? '08:00',
                'cierra'  => $base[$key]['cierra']  ?? '20:00',
                'label'   => $label,
            ];
        }

        return $resultado;
    }

    public function horarioDelDia(?Carbon $fecha = null): array
    {
        $fecha = $fecha ?: Carbon::now('America/Bogota');
        $diaKey = self::CARBON_A_DIA[$fecha->dayOfWeek] ?? 'lunes';
        $todos = $this->horariosCompletos();
        return array_merge($todos[$diaKey], ['dia_key' => $diaKey]);
    }

    /**
     * ¿La sede está abierta AHORA según los horarios configurados?
     */
    public function estaAbierta(?Carbon $momento = null): bool
    {
        $momento = $momento ?: Carbon::now('America/Bogota');
        $hoy = $this->horarioDelDia($momento);

        if (!$hoy['abierto']) return false;

        $ahora = $momento->format('H:i');
        return $ahora >= $hoy['abre'] && $ahora <= $hoy['cierra'];
    }

    /**
     * Devuelve un texto humano del horario de hoy.
     * Ej: "Hoy abierto 8:00 - 18:00" o "Hoy cerrado".
     */
    public function horarioHoyTexto(): string
    {
        $hoy = $this->horarioDelDia();
        if (!$hoy['abierto']) {
            return "Hoy ({$hoy['label']}) cerrado";
        }
        return "Hoy ({$hoy['label']}) {$hoy['abre']} - {$hoy['cierra']}";
    }

    /**
     * Devuelve el horario completo de la semana en texto multilínea.
     * Útil para inyectar al prompt del bot.
     */
    public function horariosFormateadosTexto(): string
    {
        $lineas = ["📍 {$this->nombre}" . ($this->direccion ? " — {$this->direccion}" : '')];
        foreach ($this->horariosCompletos() as $key => $h) {
            $lineas[] = $h['abierto']
                ? "  • {$h['label']}: {$h['abre']} a {$h['cierra']}"
                : "  • {$h['label']}: cerrado";
        }
        return implode("\n", $lineas);
    }

    /**
     * Devuelve la sede más cercana a un punto (lat, lng).
     * Usa fórmula haversine.
     * Si ninguna sede tiene coordenadas, devuelve la primera activa.
     */
    public static function masCercanaA(float $lat, float $lng): ?self
    {
        $sedes = self::where('activa', true)->get();

        if ($sedes->isEmpty()) {
            return null;
        }

        $conCoords = $sedes->filter(fn ($s) => $s->latitud && $s->longitud);

        // Si ninguna sede tiene coordenadas, fallback a la primera activa
        if ($conCoords->isEmpty()) {
            return $sedes->first();
        }

        return $conCoords
            ->map(function ($s) use ($lat, $lng) {
                $s->_distancia_km = self::distanciaKm($lat, $lng, $s->latitud, $s->longitud);
                return $s;
            })
            ->sortBy('_distancia_km')
            ->first();
    }

    /**
     * Haversine — distancia en km entre dos puntos.
     */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radioTierra * $c;
    }
}
