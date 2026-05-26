<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\LlamadaIvr;
use App\Models\Pedido;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 📞 API que consume Asterisk (vía AGI scripts) para registrar llamadas IVR
 *    y consultar datos de Kivox en tiempo real.
 *
 * Autenticada con X-API-KEY (middleware api.key).
 */
class IvrApiController extends Controller
{
    /**
     * POST /api/v1/ivr/llamadas/registrar
     * Inicia el registro de una llamada entrante.
     */
    public function registrar(Request $r)
    {
        $data = $r->validate([
            'caller_id' => 'required|string|max:30',
            'did'       => 'nullable|string|max:30',
            'unique_id' => 'required|string|max:60',
        ]);

        $telefono = $this->normalizar($data['caller_id']);

        // Buscar tenant por DID destino (cada tenant puede tener su número)
        $tenant = $this->tenantPorDid($data['did'] ?? null);

        // Buscar cliente
        $cliente = $tenant
            ? Cliente::where('tenant_id', $tenant->id)
                ->where('telefono_normalizado', $telefono)
                ->first()
            : null;

        $llamada = LlamadaIvr::create([
            'tenant_id'            => $tenant?->id,
            'asterisk_uniqueid'    => $data['unique_id'],
            'caller_id'            => $data['caller_id'],
            'telefono_normalizado' => $telefono,
            'did_destino'          => $data['did'] ?? null,
            'cliente_id'           => $cliente?->id,
            'estado'               => 'en_curso',
            'iniciada_at'          => now(),
            'eventos'              => [[
                'tipo' => 'inbound_start',
                'ts'   => now()->toIso8601String(),
            ]],
        ]);

        Log::info('📞 IVR llamada registrada', [
            'id'        => $llamada->id,
            'caller'    => $data['caller_id'],
            'tenant_id' => $tenant?->id,
        ]);

        return response()->json([
            'ok'         => true,
            'llamada_id' => $llamada->id,
            'tenant'     => $tenant?->slug,
            'cliente_id' => $cliente?->id,
        ]);
    }

    /**
     * POST /api/v1/ivr/llamadas/evento
     * Asterisk reporta eventos durante la llamada (consulta pedido, opción elegida, transferencia, etc.).
     */
    public function evento(Request $r)
    {
        $data = $r->validate([
            'unique_id' => 'required|string',
            'evento'    => 'required|string|max:50',
            'pedido_id' => 'nullable|integer',
            'opcion'    => 'nullable|string|max:30',
            'caller_id' => 'nullable|string|max:30',
        ]);

        $llamada = LlamadaIvr::where('asterisk_uniqueid', $data['unique_id'])->first();
        if (!$llamada) {
            return response()->json(['ok' => false, 'error' => 'llamada no encontrada'], 404);
        }

        $llamada->agregarEvento($data['evento'], array_diff_key($data, ['unique_id' => true, 'evento' => true]));

        // Actualizar campos según el tipo de evento
        if (!empty($data['opcion']))    $llamada->update(['opcion_elegida'      => $data['opcion']]);
        if (!empty($data['pedido_id'])) $llamada->update(['pedido_consultado_id'=> $data['pedido_id']]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/ivr/llamadas/finalizar
     * Asterisk marca la llamada como terminada.
     */
    public function finalizar(Request $r)
    {
        $data = $r->validate([
            'unique_id'        => 'required|string',
            'estado'           => 'required|string|max:30',
            'duracion'         => 'nullable|integer',
        ]);

        $llamada = LlamadaIvr::where('asterisk_uniqueid', $data['unique_id'])->first();
        if (!$llamada) return response()->json(['ok' => false], 404);

        $llamada->update([
            'estado'            => $data['estado'],
            'duracion_segundos' => $data['duracion'] ?? null,
            'terminada_at'      => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/v1/ivr/pedido-por-telefono/{telefono}
     * Devuelve el último pedido del cliente para reproducirlo por TTS.
     */
    public function pedidoPorTelefono(string $telefono)
    {
        $telefono = $this->normalizar($telefono);

        $pedido = Pedido::whereHas('cliente', function ($q) use ($telefono) {
                $q->where('telefono_normalizado', $telefono);
            })
            ->whereNotIn('estado', ['cancelado'])
            ->latest()
            ->first();

        if (!$pedido) {
            return response()->json(['encontrado' => false]);
        }

        return response()->json([
            'encontrado' => true,
            'pedido' => [
                'id'                 => $pedido->id,
                'estado'             => $pedido->estado,
                'estado_humano'      => $this->estadoHumano($pedido->estado),
                'total'              => $pedido->total,
                'minutos_estimados'  => $this->estimarMinutos($pedido),
                'domiciliario_nombre'=> $pedido->domiciliario?->nombre,
            ],
        ]);
    }

    /**
     * POST /api/v1/ivr/voicemail
     */
    public function voicemail(Request $r)
    {
        $data = $r->validate([
            'caller_id' => 'required|string',
            'archivo'   => 'required|string',
        ]);

        $telefono = $this->normalizar($data['caller_id']);

        $llamada = LlamadaIvr::where('telefono_normalizado', $telefono)
            ->where('estado', 'en_curso')
            ->latest()
            ->first();

        if ($llamada) {
            $llamada->update([
                'dejo_voicemail' => true,
                'voicemail_path' => $data['archivo'],
                'estado'         => 'voicemail',
            ]);
        }

        // TODO: notificar por WhatsApp/email al tenant que hay un voicemail nuevo

        return response()->json(['ok' => true]);
    }

    // ----------- helpers -----------

    private function normalizar(string $tel): string
    {
        $tel = preg_replace('/\D/', '', $tel);
        if (strlen($tel) === 10 && $tel[0] === '3') return '+57' . $tel;
        if (strlen($tel) === 12 && substr($tel, 0, 2) === '57') return '+' . $tel;
        return $tel[0] === '+' ? $tel : '+' . $tel;
    }

    private function tenantPorDid(?string $did): ?Tenant
    {
        if (!$did) return null;
        // Por ahora único — extender cuando agreguemos columna ivr_did en tenants
        return Tenant::first();
    }

    private function estadoHumano(string $estado): string
    {
        return match($estado) {
            'recibido'        => 'recibido y en preparación',
            'confirmado'      => 'confirmado',
            'en_preparacion'  => 'en preparación',
            'listo'           => 'listo para despachar',
            'en_camino'       => 'en camino',
            'entregado'       => 'entregado',
            default           => str_replace('_', ' ', $estado),
        };
    }

    private function estimarMinutos(Pedido $p): ?int
    {
        $minutos = match($p->estado) {
            'recibido'       => 35,
            'confirmado'     => 30,
            'en_preparacion' => 20,
            'listo'          => 10,
            'en_camino'      => 8,
            default          => null,
        };
        return $minutos;
    }
}
