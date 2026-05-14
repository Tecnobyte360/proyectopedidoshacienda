<?php

namespace App\Livewire\Monitoreo;

use App\Models\LlmInvocacion;
use App\Models\WatchdogRescate;
use Livewire\Component;
use Livewire\Attributes\Computed;

class Llm extends Component
{
    public int $minutos = 30;  // Ventana de análisis
    public string $tab = 'llm';  // 'llm' | 'watchdog'

    protected $queryString = [
        'tab'     => ['except' => 'llm'],
        'minutos' => ['except' => 30],
    ];

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
        ])->layout('layouts.app');
    }
}
