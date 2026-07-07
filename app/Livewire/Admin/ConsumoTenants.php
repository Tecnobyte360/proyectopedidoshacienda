<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * 📊 Dashboard de consumo por tenant.
 *
 * Muestra para cada tenant en el rango seleccionado:
 *  - Llamadas al LLM, tokens (input/output/cache), costo estimado en USD
 *  - Mensajes WhatsApp enviados/recibidos
 *  - Pedidos creados y total facturado
 *  - Invocaciones a tools del agente
 *
 * Tarifas por modelo (USD por 1M tokens) basadas en pricing público:
 *   - claude-sonnet-4.x  : $3.00 input · $15.00 output · $0.30 cache read · $3.75 cache write
 *   - claude-haiku-4.x   : $1.00 input · $5.00  output · $0.10 cache read · $1.25 cache write
 *   - gpt-4o-mini        : $0.15 input · $0.60 output
 *   - gpt-4o             : $2.50 input · $10.00 output
 */
class ConsumoTenants extends Component
{
    public string $desde = '';
    public string $hasta = '';
    public string $agrupar = 'mes'; // dia | semana | mes | total

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->endOfDay()->toDateString();
    }

    /** Precios USD por 1M tokens, por modelo. */
    private function tarifas(): array
    {
        return [
            'claude-sonnet-4-6'  => ['in' => 3.00, 'out' => 15.00, 'cache_r' => 0.30, 'cache_w' => 3.75],
            'claude-sonnet-4-5'  => ['in' => 3.00, 'out' => 15.00, 'cache_r' => 0.30, 'cache_w' => 3.75],
            'claude-sonnet-4'    => ['in' => 3.00, 'out' => 15.00, 'cache_r' => 0.30, 'cache_w' => 3.75],
            'claude-haiku-4-5'   => ['in' => 1.00, 'out' => 5.00,  'cache_r' => 0.10, 'cache_w' => 1.25],
            'claude-haiku-4'     => ['in' => 1.00, 'out' => 5.00,  'cache_r' => 0.10, 'cache_w' => 1.25],
            'claude-opus-4'      => ['in' => 15.00,'out' => 75.00, 'cache_r' => 1.50, 'cache_w' => 18.75],
            'gpt-4o-mini'        => ['in' => 0.15, 'out' => 0.60,  'cache_r' => 0.075,'cache_w' => 0.0],
            'gpt-4o'             => ['in' => 2.50, 'out' => 10.00, 'cache_r' => 1.25, 'cache_w' => 0.0],
            'gpt-4'              => ['in' => 30.00,'out' => 60.00, 'cache_r' => 0.0,  'cache_w' => 0.0],
        ];
    }

    private function tarifaDe(string $modelo): array
    {
        $tarifas = $this->tarifas();
        if (isset($tarifas[$modelo])) return $tarifas[$modelo];
        // Fallback por prefijo
        foreach ($tarifas as $k => $v) {
            if (str_starts_with($modelo, $k)) return $v;
        }
        // Default conservador (Sonnet)
        return ['in' => 3.00, 'out' => 15.00, 'cache_r' => 0.30, 'cache_w' => 3.75];
    }

    private function costoLlamada(string $modelo, int $tIn, int $tOut, int $tCacheR, int $tCacheW = 0): float
    {
        $t = $this->tarifaDe($modelo);
        return (
            ($tIn * $t['in']) +
            ($tOut * $t['out']) +
            ($tCacheR * $t['cache_r']) +
            ($tCacheW * $t['cache_w'])
        ) / 1_000_000;
    }

    public function getDatosProperty(): array
    {
        $desde = $this->desde . ' 00:00:00';
        $hasta = $this->hasta . ' 23:59:59';

        $tenants = Tenant::query()
            ->orderBy('id')
            ->get(['id', 'nombre', 'slug', 'plan', 'activo']);

        // LLM agregado por tenant + modelo
        $llmRows = DB::table('llm_invocaciones')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('tenant_id', 'provider', 'modelo')
            ->select(
                'tenant_id',
                'provider',
                'modelo',
                DB::raw('COUNT(*) as llamadas'),
                DB::raw('SUM(CASE WHEN exitoso=1 THEN 1 ELSE 0 END) as exitosas'),
                DB::raw('SUM(tokens_input) as tk_in'),
                DB::raw('SUM(tokens_output) as tk_out'),
                DB::raw('SUM(tokens_cache_read) as tk_cache_r'),
                DB::raw('SUM(tokens_cache_creation) as tk_cache_w'),
                DB::raw('AVG(latencia_ms) as lat_ms'),
            )
            ->get();

        // Mensajes WhatsApp
        $msgRows = DB::table('mensajes_whatsapp as m')
            ->join('conversaciones_whatsapp as c', 'm.conversacion_id', '=', 'c.id')
            ->whereBetween('m.created_at', [$desde, $hasta])
            ->groupBy('c.tenant_id', 'm.rol')
            ->select('c.tenant_id', 'm.rol', DB::raw('COUNT(*) as total'))
            ->get();

        // Pedidos creados
        $pedRows = DB::table('pedidos')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('tenant_id')
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as total_pedidos'),
                DB::raw('SUM(total) as facturado'),
            )
            ->get()
            ->keyBy('tenant_id');

        // Tools del agente
        $toolRows = DB::table('agente_tool_invocaciones')
            ->whereBetween('created_at', [$desde, $hasta])
            ->groupBy('tenant_id')
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as total_tools'),
                DB::raw('SUM(CASE WHEN exitoso=1 THEN 1 ELSE 0 END) as tools_ok'),
            )
            ->get()
            ->keyBy('tenant_id');

        // Construcción consolidada por tenant
        $result = [];
        $totalGeneral = [
            'llamadas' => 0, 'tk_in' => 0, 'tk_out' => 0, 'tk_cache_r' => 0,
            'tk_cache_w' => 0, 'costo_usd' => 0, 'msgs_user' => 0, 'msgs_bot' => 0,
            'pedidos' => 0, 'facturado' => 0, 'tools' => 0,
        ];

        foreach ($tenants as $t) {
            $row = [
                'tenant'      => $t,
                'llamadas'    => 0, 'exitosas' => 0,
                'tk_in'       => 0, 'tk_out' => 0, 'tk_cache_r' => 0, 'tk_cache_w' => 0,
                'costo_usd'   => 0.0,
                'modelos'     => [],
                'msgs_user'   => 0, 'msgs_bot' => 0,
                'pedidos'     => (int) ($pedRows[$t->id]->total_pedidos ?? 0),
                'facturado'   => (float) ($pedRows[$t->id]->facturado ?? 0),
                'tools'       => (int) ($toolRows[$t->id]->total_tools ?? 0),
                'tools_ok'    => (int) ($toolRows[$t->id]->tools_ok ?? 0),
                'lat_ms_avg'  => 0,
            ];

            $latSum = 0;
            $latN = 0;

            foreach ($llmRows->where('tenant_id', $t->id) as $r) {
                $row['llamadas']   += $r->llamadas;
                $row['exitosas']   += $r->exitosas;
                $row['tk_in']      += (int) $r->tk_in;
                $row['tk_out']     += (int) $r->tk_out;
                $row['tk_cache_r'] += (int) $r->tk_cache_r;
                $row['tk_cache_w'] += (int) $r->tk_cache_w;

                $costoModelo = $this->costoLlamada(
                    $r->modelo,
                    (int) $r->tk_in,
                    (int) $r->tk_out,
                    (int) $r->tk_cache_r,
                    (int) $r->tk_cache_w,
                );
                $row['costo_usd'] += $costoModelo;
                $row['modelos'][] = [
                    'provider' => $r->provider,
                    'modelo'   => $r->modelo,
                    'llamadas' => $r->llamadas,
                    'tk_in'    => (int) $r->tk_in,
                    'tk_out'   => (int) $r->tk_out,
                    'costo'    => $costoModelo,
                ];
                if ($r->lat_ms) { $latSum += $r->lat_ms * $r->llamadas; $latN += $r->llamadas; }
            }
            $row['lat_ms_avg'] = $latN > 0 ? (int) round($latSum / $latN) : 0;

            foreach ($msgRows->where('tenant_id', $t->id) as $m) {
                if ($m->rol === 'user') $row['msgs_user'] += $m->total;
                elseif ($m->rol === 'assistant') $row['msgs_bot'] += $m->total;
            }

            $result[] = $row;

            // Totales
            $totalGeneral['llamadas']   += $row['llamadas'];
            $totalGeneral['tk_in']      += $row['tk_in'];
            $totalGeneral['tk_out']     += $row['tk_out'];
            $totalGeneral['tk_cache_r'] += $row['tk_cache_r'];
            $totalGeneral['tk_cache_w'] += $row['tk_cache_w'];
            $totalGeneral['costo_usd']  += $row['costo_usd'];
            $totalGeneral['msgs_user']  += $row['msgs_user'];
            $totalGeneral['msgs_bot']   += $row['msgs_bot'];
            $totalGeneral['pedidos']    += $row['pedidos'];
            $totalGeneral['facturado']  += $row['facturado'];
            $totalGeneral['tools']      += $row['tools'];
        }

        // Ordenar por costo descendente
        usort($result, fn ($a, $b) => $b['costo_usd'] <=> $a['costo_usd']);

        return ['tenants' => $result, 'total' => $totalGeneral];
    }

    public function setRango(string $rango): void
    {
        switch ($rango) {
            case 'hoy':
                $this->desde = now()->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case '7d':
                $this->desde = now()->subDays(7)->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case '30d':
                $this->desde = now()->subDays(30)->toDateString();
                $this->hasta = now()->toDateString();
                break;
            case 'mes':
                $this->desde = now()->startOfMonth()->toDateString();
                $this->hasta = now()->endOfMonth()->toDateString();
                break;
            case 'mes_anterior':
                $this->desde = now()->subMonth()->startOfMonth()->toDateString();
                $this->hasta = now()->subMonth()->endOfMonth()->toDateString();
                break;
        }
    }

    public function render()
    {
        return view('livewire.admin.consumo-tenants', [
            'datos' => $this->datos,
        ])->layout('layouts.app');
    }
}
