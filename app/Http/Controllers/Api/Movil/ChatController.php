<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\Tenant;
use App\Services\ConversacionService;
use App\Services\Meta\MetaWhatsappCloudService;
use App\Services\TenantManager;
use Illuminate\Http\Request;

/**
 * Chat de WhatsApp para la app móvil (Fase 1: texto).
 * Respeta tenant + permiso 'chat.usar'.
 */
class ChatController extends Controller
{
    /** Fija el tenant del usuario autenticado y exige permiso de chat. */
    private function ctx(Request $r)
    {
        $u = $r->user();
        $t = Tenant::withoutGlobalScopes()->find($u->tenant_id);
        if ($t) app(TenantManager::class)->set($t);
        return $u;
    }

    public function conversaciones(Request $r)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) {
            return response()->json(['ok' => false, 'message' => 'No tienes permiso para el chat.'], 403);
        }

        $q = trim((string) $r->input('q', ''));
        $convs = ConversacionWhatsapp::with('cliente')
            ->when($q !== '', fn ($x) => $x->where(function ($s) use ($q) {
                $s->whereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%"))
                  ->orWhere('telefono_normalizado', 'like', "%{$q}%");
            }))
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(60)->get();

        return response()->json(['ok' => true, 'conversaciones' => $convs->map(function ($c) {
            $ultimo = $c->mensajes()->orderByDesc('id')->first();
            return [
                'id'        => $c->id,
                'nombre'    => $c->cliente->nombre ?? 'Cliente',
                'telefono'  => $c->telefono_visible ?? $c->telefono_normalizado,
                'canal'     => $c->canal ?: 'whatsapp',
                'no_leidos' => (int) ($c->no_leidos ?? 0),
                'ultimo'    => $ultimo ? mb_substr((string) $ultimo->contenido, 0, 60) : '',
                'ultimo_at' => optional($c->ultimo_mensaje_at)->toIso8601String(),
            ];
        })->values()]);
    }

    public function mensajes(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) {
            return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        }
        $conv = ConversacionWhatsapp::with('cliente')->find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $msgs = $conv->mensajes()->orderBy('id')->limit(300)->get();
        try { $conv->update(['no_leidos' => 0]); } catch (\Throwable $e) {}

        return response()->json([
            'ok'      => true,
            'cliente' => $conv->cliente->nombre ?? 'Cliente',
            'telefono'=> $conv->telefono_visible ?? $conv->telefono_normalizado,
            'mensajes' => $msgs->map(fn ($m) => [
                'id'        => $m->id,
                'contenido' => (string) $m->contenido,
                'tipo'      => $m->tipo,
                'mio'       => in_array($m->rol, [MensajeWhatsapp::ROL_ASSISTANT], true),
                'at'        => optional($m->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function enviar(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) {
            return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        }
        $r->validate(['texto' => 'required|string|max:4000']);

        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $texto  = trim($r->texto);
        $tenant = app(TenantManager::class)->current();
        $tel    = preg_replace('/\D+/', '', (string) $conv->telefono_normalizado);

        $ok = false;
        try {
            $ok = app(MetaWhatsappCloudService::class)->enviarTexto($tel, $texto, $tenant->id);
        } catch (\Throwable $e) {
            \Log::warning('Chat móvil enviar: ' . $e->getMessage());
        }
        if (!$ok) {
            return response()->json(['ok' => false, 'message' => 'No se pudo enviar. La ventana de 24h puede estar cerrada (usa la plataforma para enviar plantilla).'], 422);
        }

        $m = app(ConversacionService::class)->agregarMensaje(
            $conv, MensajeWhatsapp::ROL_ASSISTANT, $texto,
            ['meta' => ['enviado_por_humano' => true, 'usuario_id' => $u->id, 'origen' => 'app_movil']]
        );
        try { $m->update(['ack' => MensajeWhatsapp::ACK_SENT]); } catch (\Throwable $e) {}

        return response()->json(['ok' => true, 'mensaje' => [
            'id' => $m->id, 'contenido' => $texto, 'tipo' => 'text', 'mio' => true,
            'at' => now()->toIso8601String(),
        ]]);
    }
}
