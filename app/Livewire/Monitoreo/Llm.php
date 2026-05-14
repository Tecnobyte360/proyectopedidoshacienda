<?php

namespace App\Livewire\Monitoreo;

use App\Models\AgenteToolInvocacion;
use App\Models\LlmInvocacion;
use App\Models\WatchdogRescate;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Computed;

class Llm extends Component
{
    public int $minutos = 30;  // Ventana de análisis
    public string $tab = 'llm';  // 'llm' | 'watchdog' | 'agente' | 'envivo' | 'cola'

    protected $queryString = [
        'tab'     => ['except' => 'llm'],
        'minutos' => ['except' => 30],
    ];

    /**
     * 📬 Reintentar manualmente un mensaje pendiente.
     */
    public function reintentarMensajePendiente(int $id): void
    {
        DB::table('mensajes_salida_pendientes')
            ->where('id', $id)
            ->whereNull('enviado_at')
            ->update([
                'proximo_intento_at' => now()->subSecond(),
                'updated_at' => now(),
            ]);
        \Artisan::call('bot:reintentar-mensajes-salida');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Reintento ejecutado.']);
    }

    /**
     * 🗑️ Descartar un mensaje pendiente (marcarlo como fallido_permanente).
     */
    public function descartarMensajePendiente(int $id): void
    {
        DB::table('mensajes_salida_pendientes')
            ->where('id', $id)
            ->whereNull('enviado_at')
            ->update([
                'fallido_permanente_at' => now(),
                'ultimo_error' => 'Descartado manualmente desde panel',
                'updated_at' => now(),
            ]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Mensaje descartado.']);
    }

    /**
     * 🧹 Limpiar mensajes enviados/fallidos antiguos (>7 días).
     */
    public function limpiarColaSalida(): void
    {
        $borrados = DB::table('mensajes_salida_pendientes')
            ->where(function ($q) {
                $q->whereNotNull('enviado_at')->orWhereNotNull('fallido_permanente_at');
            })
            ->where('updated_at', '<', now()->subDays(7))
            ->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Eliminados {$borrados} registros antiguos."]);
    }

    public function render()
    {
        $desde = now()->subMinutes($this->minutos);

        $invocaciones = LlmInvocacion::where('created_at', '>=', $desde)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $total = LlmInvocacion::where('created_at', '>=', $desde)->count();
        $exitosos = LlmInvocacion::where('created_at', '>=', $desde)->where('exitoso', true)->count();
        $rateLimit = LlmInvocacion::where('created_at', '>=', $desde)->where('http_status', 429)->count();
        $overloaded = LlmInvocacion::where('created_at', '>=', $desde)->where('http_status', 529)->count();
        $errores = $total - $exitosos;
        $fallbacks = LlmInvocacion::where('created_at', '>=', $desde)->where('es_fallback', true)->count();

        $tokensInput = (int) LlmInvocacion::where('created_at', '>=', $desde)->sum('tokens_input');
        $tokensOutput = (int) LlmInvocacion::where('created_at', '>=', $desde)->sum('tokens_output');
        $tokensCacheRead = (int) LlmInvocacion::where('created_at', '>=', $desde)->sum('tokens_cache_read');
        $tokensCacheCreate = (int) LlmInvocacion::where('created_at', '>=', $desde)->sum('tokens_cache_creation');

        $latPromedio = (int) LlmInvocacion::where('created_at', '>=', $desde)
            ->where('exitoso', true)
            ->avg('latencia_ms');

        // Tokens del último minuto (para mostrar consumo vs rate limit)
        $tokensUltimoMin = (int) LlmInvocacion::where('created_at', '>=', now()->subMinute())
            ->selectRaw('SUM(COALESCE(tokens_input,0) + COALESCE(tokens_cache_creation,0)) as t')
            ->value('t');

        $rateLimitTier = 450000; // Tier 2 actual — sube a 1M+ en Tier 3
        $porcentajeUso = $rateLimitTier > 0
            ? min(100, round(($tokensUltimoMin / $rateLimitTier) * 100, 1))
            : 0;

        // Estado actual de la API
        $estado = match (true) {
            $overloaded > 0 && (now()->diffInMinutes(LlmInvocacion::where('http_status', 529)->latest('id')->value('created_at') ?? now()->subYear()) < 2) => 'overloaded',
            $rateLimit  > 0 && (now()->diffInMinutes(LlmInvocacion::where('http_status', 429)->latest('id')->value('created_at') ?? now()->subYear()) < 2) => 'rate_limit',
            $errores == 0 && $exitosos > 0 => 'ok',
            default => 'ok',
        };

        $tasaExito = $total > 0 ? round(($exitosos / $total) * 100, 1) : 100;

        // 🤖 AGENTE — KPIs e invocaciones de tools (misma ventana)
        $agQuery = AgenteToolInvocacion::where('created_at', '>=', $desde);
        $agTotal       = (clone $agQuery)->count();
        $agExitosos    = (clone $agQuery)->where('exitoso', true)->count();
        $agLatencia    = (int) (clone $agQuery)->avg('latencia_ms');
        $agSinResults  = (clone $agQuery)->where('count_resultados', 0)->where('exitoso', true)->count();
        $agTasaExito   = $agTotal > 0 ? round(($agExitosos / $agTotal) * 100, 1) : 100;
        $agPorTool     = (clone $agQuery)
            ->select('tool_name', DB::raw('COUNT(*) as total'), DB::raw('AVG(latencia_ms) as latencia'))
            ->groupBy('tool_name')
            ->orderByDesc('total')
            ->get();
        $agMaxPorTool  = $agPorTool->max('total') ?: 1;
        $agTopQueries  = (clone $agQuery)
            ->where('tool_name', 'buscar_productos')
            ->get()
            ->map(fn ($i) => trim((string) ($i->args['query'] ?? '')))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(8);
        $agInvocaciones = (clone $agQuery)->orderByDesc('id')->limit(40)->get();

        // 🐕 WATCHDOG — KPIs y rescates recientes (misma ventana que LLM)
        $wdQuery = WatchdogRescate::where('created_at', '>=', $desde);
        $wdTotal      = (clone $wdQuery)->count();
        $wdExitosos   = (clone $wdQuery)->where('exitoso', true)->count();
        $wdFallidos   = $wdTotal - $wdExitosos;
        $wdPromSegs   = (int) round((clone $wdQuery)->avg('segundos_estancada') ?: 0);
        $wdClientes   = (clone $wdQuery)->distinct('telefono')->count('telefono');
        $wdTasaExito  = $wdTotal > 0 ? round(($wdExitosos / $wdTotal) * 100, 1) : 100;
        $wdUltimo     = (clone $wdQuery)->orderByDesc('id')->first();
        $wdRescates   = (clone $wdQuery)
            ->with(['conversacion:id,cliente_id,telefono_normalizado', 'conversacion.cliente:id,nombre'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // 📬 COLA DE SALIDA — KPIs y mensajes recientes
        $coTabla = 'mensajes_salida_pendientes';
        $coPendientes = DB::table($coTabla)
            ->whereNull('enviado_at')
            ->whereNull('fallido_permanente_at')
            ->count();
        $coReady = DB::table($coTabla)
            ->whereNull('enviado_at')
            ->whereNull('fallido_permanente_at')
            ->where(function ($q) {
                $q->whereNull('proximo_intento_at')
                  ->orWhere('proximo_intento_at', '<=', now());
            })
            ->count();
        $coEnviadosVentana = DB::table($coTabla)
            ->whereNotNull('enviado_at')
            ->where('updated_at', '>=', $desde)
            ->count();
        $coFallidosPerm = DB::table($coTabla)
            ->whereNotNull('fallido_permanente_at')
            ->where('updated_at', '>=', $desde)
            ->count();
        $coUltimosMensajes = DB::table($coTabla)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        // Estados WhatsApp por conexión (lo que vemos de la API)
        $coEstadoWa = DB::table($coTabla)
            ->selectRaw('connection_id, COUNT(*) as total')
            ->whereNull('enviado_at')
            ->whereNull('fallido_permanente_at')
            ->groupBy('connection_id')
            ->get();

        return view('livewire.monitoreo.llm', [
            'invocaciones'      => $invocaciones,
            'total'             => $total,
            'exitosos'          => $exitosos,
            'errores'           => $errores,
            'rateLimit'         => $rateLimit,
            'overloaded'        => $overloaded,
            'fallbacks'         => $fallbacks,
            'tokensInput'       => $tokensInput,
            'tokensOutput'      => $tokensOutput,
            'tokensCacheRead'   => $tokensCacheRead,
            'tokensCacheCreate' => $tokensCacheCreate,
            'latPromedio'       => $latPromedio,
            'tokensUltimoMin'   => $tokensUltimoMin,
            'rateLimitTier'     => $rateLimitTier,
            'porcentajeUso'     => $porcentajeUso,
            'estado'            => $estado,
            'tasaExito'         => $tasaExito,
            // 🐕 Watchdog data
            'wdTotal'           => $wdTotal,
            'wdExitosos'        => $wdExitosos,
            'wdFallidos'        => $wdFallidos,
            'wdPromSegs'        => $wdPromSegs,
            'wdClientes'        => $wdClientes,
            'wdTasaExito'       => $wdTasaExito,
            'wdUltimo'          => $wdUltimo,
            'wdRescates'        => $wdRescates,
            // 🤖 Agente data
            'agTotal'           => $agTotal,
            'agExitosos'        => $agExitosos,
            'agLatencia'        => $agLatencia,
            'agSinResults'      => $agSinResults,
            'agTasaExito'       => $agTasaExito,
            'agPorTool'         => $agPorTool,
            'agMaxPorTool'      => $agMaxPorTool,
            'agTopQueries'      => $agTopQueries,
            'agInvocaciones'    => $agInvocaciones,
            // 📬 Cola de salida data
            'coPendientes'      => $coPendientes,
            'coReady'           => $coReady,
            'coEnviadosVentana' => $coEnviadosVentana,
            'coFallidosPerm'    => $coFallidosPerm,
            'coUltimosMensajes' => $coUltimosMensajes,
            'coEstadoWa'        => $coEstadoWa,
        ])->layout('layouts.app');
    }
}
