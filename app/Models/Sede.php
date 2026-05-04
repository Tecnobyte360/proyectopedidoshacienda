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
        'whatsapp_connection_id',
        'whatsapp_id',
        'whatsapp_telefono',
        // Cobertura propia de la sede (refactor: cada sede su area)
        'cobertura_poligono',
        'cobertura_costo_envio',
        'cobertura_tiempo_min',
        'cobertura_pedido_minimo',
        'cobertura_color',
        'cobertura_descripcion',
        'cobertura_activa',
        'cobertura_centro_lat',
        'cobertura_centro_lng',
    ];

    protected $casts = [
        'latitud'  => 'float',
        'longitud' => 'float',
        'activa'   => 'boolean',
        'horarios' => 'array',
        'whatsapp_connection_id' => 'integer',
        'whatsapp_id'            => 'integer',
        'cobertura_poligono'     => 'array',
        'cobertura_costo_envio'  => 'float',
        'cobertura_tiempo_min'   => 'integer',
        'cobertura_pedido_minimo' => 'float',
        'cobertura_activa'       => 'boolean',
        'cobertura_centro_lat'   => 'float',
        'cobertura_centro_lng'   => 'float',
    ];

    /**
     * ¿Esta sede tiene polígono(s) de cobertura definido(s)?
     * Soporta tanto formato simple [[lat,lng],...] como multi [[[lat,lng]...],...].
     */
    public function tieneCobertura(): bool
    {
        $polys = $this->poligonosNormalizados();
        foreach ($polys as $p) {
            if (is_array($p) && count($p) >= 3) return true;
        }
        return false;
    }

    /**
     * Devuelve SIEMPRE un array de polígonos (multi-zona).
     * Si la cobertura está guardada como polígono único legacy, lo envuelve.
     *
     * Formato siempre garantizado: [[[lat,lng], ...], [[lat,lng], ...], ...]
     */
    public function poligonosNormalizados(): array
    {
        $p = $this->cobertura_poligono;
        if (!is_array($p) || empty($p)) return [];

        $primero = $p[0] ?? null;
        if (!is_array($primero)) return [];

        // Detectar formato:
        //  - simple: [[lat,lng], [lat,lng], ...] → primero es [lat,lng] (2 numéricos)
        //  - multi:  [[[lat,lng], ...], ...]    → primero es array de puntos
        $primerSubElemento = $primero[0] ?? null;

        if (is_array($primerSubElemento)) {
            // Ya es multi
            return $p;
        }

        // Es simple → wrap
        return [$p];
    }

    /**
     * Calcula distancia haversine en km desde la sede hasta un punto.
     * Usa la ubicación física (latitud/longitud) de la sede.
     */
    public function distanciaA(float $lat, float $lng): float
    {
        if (!$this->latitud || !$this->longitud) return PHP_FLOAT_MAX;
        $earthRadius = 6371; // km
        $latFrom = deg2rad((float) $this->latitud);
        $lngFrom = deg2rad((float) $this->longitud);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);
        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;
        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Busca la sede que tiene asignado el connection_id recibido
     * (para rutear conversaciones entrantes al lugar correcto).
     */
    public static function porConnectionId(int $connectionId): ?self
    {
        return self::where('whatsapp_connection_id', $connectionId)
            ->orWhere('whatsapp_id', $connectionId)
            ->first();
    }

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
     * Devuelve el próximo momento en que la sede abrirá, en texto humano.
     * Ej: "mañana lunes a las 8:00" o "el sábado a las 9:00".
     * Devuelve null si la sede no abre ningún día (raro).
     */
    public function proximaApertura(?Carbon $desde = null): ?string
    {
        $desde = $desde ?: Carbon::now('America/Bogota');
        $todos = $this->horariosCompletos();

        // Si hoy abre y aún no ha pasado la hora de apertura, devolver eso.
        $hoyKey = self::CARBON_A_DIA[$desde->dayOfWeek] ?? 'lunes';
        $hoyConf = $todos[$hoyKey] ?? null;
        if ($hoyConf && $hoyConf['abierto']) {
            $abreHoy = Carbon::parse($desde->format('Y-m-d') . ' ' . $hoyConf['abre'], 'America/Bogota');
            if ($desde->lt($abreHoy)) {
                return "hoy a las {$hoyConf['abre']}";
            }
        }

        // Buscar en los próximos 7 días el siguiente que esté abierto.
        for ($i = 1; $i <= 7; $i++) {
            $futuro = $desde->copy()->addDays($i);
            $dKey = self::CARBON_A_DIA[$futuro->dayOfWeek] ?? 'lunes';
            $conf = $todos[$dKey] ?? null;
            if ($conf && $conf['abierto']) {
                $etiqueta = $i === 1 ? 'mañana' : ('el ' . ($conf['label'] ?? $dKey));
                return mb_strtolower($etiqueta) . " a las {$conf['abre']}";
            }
        }
        return null;
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
