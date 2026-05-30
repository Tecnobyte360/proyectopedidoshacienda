<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInformeConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 📊 Genera el informe periódico de salud del negocio para enviar al admin
 * del tenant. Calcula métricas relevantes y devuelve un array estructurado
 * que la vista del email puede renderizar.
 */
class InformeNegocioService
{
    /**
     * Genera el informe para un tenant. La ventana de análisis depende de la
     * frecuencia: diario=24h, semanal=7d, mensual=30d.
     */
    public function generar(Tenant $tenant, string $frecuencia = 'semanal'): array
    {
        $dias = match ($frecuencia) {
            'diario'  => 1,
            'mensual' => 30,
            default   => 7,
        };
        $desde = now()->subDays($dias);
        $hasta = now();
        $tid = $tenant->id;

        return [
            'tenant'        => $tenant,
            'rango'         => ['desde' => $desde, 'hasta' => $hasta, 'dias' => $dias, 'frecuencia' => $frecuencia],
            'volumen'       => $this->volumen($tid, $desde),
            'horasPico'     => $this->horasPico($tid, $desde),
            'tiempoResp'    => $this->tiempoRespuesta($tid, $desde),
            'reacciones'    => $this->reacciones($tid, $desde),
            'topClientes'   => $this->topClientes($tid, $desde),
            'sinResponder'  => $this->sinResponder($tid, $umbralMin: 120),
            'palabrasTop'   => $this->palabrasTop($tid, $desde),
            'campanas'      => $this->campanasResumen($tid, $desde),
            'costoMeta'     => $this->costoMeta($tid, $desde),
        ];
    }

    private function volumen(int $tid, Carbon $desde): array
    {
        $msgs = DB::table('mensajes_whatsapp as m')
            ->join('conversaciones_whatsapp as c', 'c.id', '=', 'm.conversacion_id')
            ->where('c.tenant_id', $tid)
            ->where('m.created_at', '>=', $desde);

        $totalMsg = (clone $msgs)->count();
        $totalCliente = (clone $msgs)->where('m.rol', 'user')->count();
        $totalBot = (clone $msgs)->where('m.rol', 'assistant')->count();
        $conversaciones = DB::table('conversaciones_whatsapp')
            ->where('tenant_id', $tid)->count();
        $convsActivas = DB::table('conversaciones_whatsapp')
            ->where('tenant_id', $tid)
            ->where('ultima_actividad_at', '>=', $desde)
            ->count();
        $nuevasConvs = DB::table('conversaciones_whatsapp')
            ->where('tenant_id', $tid)
            ->where('created_at', '>=', $desde)
            ->count();

        return [
            'total_mensajes'   => $totalMsg,
            'cliente_msgs'    => $totalCliente,
            'operador_msgs'   => $totalBot,
            'conversaciones'   => $conversaciones,
            'convs_activas'   => $convsActivas,
            'convs_nuevas'    => $nuevasConvs,
        ];
    }

    private function horasPico(int $tid, Carbon $desde): array
    {
        $rows = DB::select("
            SELECT HOUR(m.created_at) AS hora, COUNT(*) AS n
            FROM mensajes_whatsapp m
            JOIN conversaciones_whatsapp c ON c.id = m.conversacion_id
            WHERE c.tenant_id = ? AND m.rol='user' AND m.created_at >= ?
            GROUP BY hora ORDER BY hora
        ", [$tid, $desde]);

        $serie = [];
        foreach ($rows as $r) $serie[(int) $r->hora] = (int) $r->n;

        // Ventana pico = 3 horas consecutivas con mayor suma
        $maxSuma = 0; $pico = ['inicio' => 0, 'fin' => 0, 'suma' => 0];
        for ($h = 0; $h <= 21; $h++) {
            $suma = ($serie[$h] ?? 0) + ($serie[$h+1] ?? 0) + ($serie[$h+2] ?? 0);
            if ($suma > $maxSuma) {
                $maxSuma = $suma;
                $pico = ['inicio' => $h, 'fin' => $h+3, 'suma' => $suma];
            }
        }

        return ['serie' => $serie, 'pico' => $pico];
    }

    private function tiempoRespuesta(int $tid, Carbon $desde): array
    {
        $row = DB::select("
            SELECT
              AVG(diff_seg) AS prom_seg,
              MIN(diff_seg) AS min_seg,
              MAX(diff_seg) AS max_seg,
              COUNT(*) AS n
            FROM (
              SELECT
                TIMESTAMPDIFF(SECOND, m1.created_at, (
                  SELECT MIN(m2.created_at) FROM mensajes_whatsapp m2
                  WHERE m2.conversacion_id = m1.conversacion_id
                    AND m2.rol='assistant' AND m2.created_at > m1.created_at
                )) AS diff_seg
              FROM mensajes_whatsapp m1
              JOIN conversaciones_whatsapp c ON c.id = m1.conversacion_id
              WHERE c.tenant_id = ? AND m1.rol='user' AND m1.created_at >= ?
            ) t
            WHERE diff_seg IS NOT NULL AND diff_seg BETWEEN 0 AND 86400
        ", [$tid, $desde]);

        if (empty($row)) return ['prom_min' => 0, 'min_seg' => 0, 'max_min' => 0, 'muestras' => 0];
        $r = $row[0];
        return [
            'prom_min' => round(($r->prom_seg ?? 0) / 60, 1),
            'min_seg'  => (int) ($r->min_seg ?? 0),
            'max_min'  => round(($r->max_seg ?? 0) / 60, 1),
            'muestras' => (int) ($r->n ?? 0),
        ];
    }

    private function reacciones(int $tid, Carbon $desde): array
    {
        return DB::table('mensajes_whatsapp as m')
            ->join('conversaciones_whatsapp as c', 'c.id', '=', 'm.conversacion_id')
            ->where('c.tenant_id', $tid)
            ->where('m.created_at', '>=', $desde)
            ->whereNotNull('m.reaccion_cliente')
            ->selectRaw('m.reaccion_cliente as emoji, COUNT(*) as n')
            ->groupBy('m.reaccion_cliente')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function topClientes(int $tid, Carbon $desde, int $limit = 5): array
    {
        return DB::select("
            SELECT c.telefono_normalizado AS telefono,
                   COUNT(m.id) AS total_msgs,
                   SUM(CASE WHEN m.rol='user' THEN 1 ELSE 0 END) AS cliente_msgs
            FROM mensajes_whatsapp m
            JOIN conversaciones_whatsapp c ON c.id = m.conversacion_id
            WHERE c.tenant_id = ? AND m.created_at >= ?
            GROUP BY c.telefono_normalizado
            ORDER BY total_msgs DESC LIMIT ?
        ", [$tid, $desde, $limit]);
    }

    private function sinResponder(int $tid, int $umbralMin = 120): array
    {
        return DB::select("
            SELECT c.telefono_normalizado AS telefono,
                   MAX(m.created_at) AS ult_msg,
                   TIMESTAMPDIFF(MINUTE, MAX(m.created_at), NOW()) AS min_sin_resp
            FROM mensajes_whatsapp m
            JOIN conversaciones_whatsapp c ON c.id = m.conversacion_id
            WHERE c.tenant_id = ? AND m.rol='user'
              AND m.created_at >= NOW() - INTERVAL 7 DAY
              AND NOT EXISTS (
                SELECT 1 FROM mensajes_whatsapp m2
                WHERE m2.conversacion_id = m.conversacion_id
                  AND m2.rol='assistant' AND m2.created_at > m.created_at
              )
            GROUP BY c.telefono_normalizado
            HAVING min_sin_resp > ?
            ORDER BY min_sin_resp DESC LIMIT 10
        ", [$tid, $umbralMin]);
    }

    private function palabrasTop(int $tid, Carbon $desde, int $limit = 15): array
    {
        $msgs = DB::table('mensajes_whatsapp as m')
            ->join('conversaciones_whatsapp as c', 'c.id', '=', 'm.conversacion_id')
            ->where('c.tenant_id', $tid)
            ->where('m.rol', 'user')
            ->where('m.created_at', '>=', $desde)
            ->whereRaw('LENGTH(m.contenido) BETWEEN 2 AND 500')
            ->pluck('m.contenido');

        $stop = ['de','la','el','en','y','a','los','del','las','un','una','para','por','con','que','se','no','su','al','lo','como','mas','más','o','si','sí','le','ya','muy','está','este','esta','son','ser','sus','solo','hay','sin','vamos','está','dame','me','mi','te','tu','quiero','puedo','puedes','podrias','puede','va','q','pa','tambien','también','aqui','aquí','ahora','vos','si','ese','esa','sí','dame','soy','estoy','bien','gracias','hola','buenas','buenos','dias','días','tardes','tarde','noches','noche','favor','muchas','com','pero','imagen','ustedes','hacer','porfa','sería','cuánto'];
        $freq = [];
        foreach ($msgs as $t) {
            $t = mb_strtolower(strip_tags((string) $t));
            $t = preg_replace('/[^a-záéíóúüñ\s]/u', ' ', $t);
            foreach (preg_split('/\s+/u', $t) as $w) {
                if (mb_strlen($w) < 3) continue;
                if (in_array($w, $stop, true)) continue;
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
        }
        arsort($freq);
        return array_slice($freq, 0, $limit, true);
    }

    private function campanasResumen(int $tid, Carbon $desde): array
    {
        $row = DB::table('campanas_whatsapp')
            ->where('tenant_id', $tid)
            ->where('created_at', '>=', $desde)
            ->selectRaw('COUNT(*) as total, SUM(total_enviados) as enviados, SUM(total_fallidos) as fallidos')
            ->first();
        return [
            'total'    => (int) ($row->total ?? 0),
            'enviados' => (int) ($row->enviados ?? 0),
            'fallidos' => (int) ($row->fallidos ?? 0),
        ];
    }

    private function costoMeta(int $tid, Carbon $desde): array
    {
        $row = DB::table('whatsapp_billing_events')
            ->where('tenant_id', $tid)
            ->where('ocurrido_at', '>=', $desde)
            ->where('billable', true)
            ->selectRaw('COUNT(*) as total, SUM(cost_usd) as usd')
            ->first();
        return [
            'conversaciones' => (int) ($row->total ?? 0),
            'usd'            => round((float) ($row->usd ?? 0), 4),
            'cop'            => (int) round(((float) ($row->usd ?? 0)) * 4150),
        ];
    }
}
