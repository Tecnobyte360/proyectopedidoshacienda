<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\MetaWhatsappPlantilla;
use App\Models\Tenant;
use App\Services\ConversacionService;
use App\Services\Meta\MetaWhatsappCloudService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Chat de WhatsApp para la app móvil: texto, fotos, audios, documentos y plantillas.
 * Respeta tenant + permiso 'chat.usar'.
 */
class ChatController extends Controller
{
    private function ctx(Request $r)
    {
        $u = $r->user();
        $t = Tenant::withoutGlobalScopes()->find($u->tenant_id);
        if ($t) app(TenantManager::class)->set($t);
        return $u;
    }

    private function metaArr($m): array
    {
        $meta = $m->meta;
        if (is_array($meta)) return $meta;
        $d = json_decode((string) $meta, true);
        return is_array($d) ? $d : [];
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
                'favorito'  => $c->fijada_at !== null,
                'no_leida'  => (bool) ($c->marcada_no_leida ?? false),
                'ultimo'    => $ultimo ? mb_substr((string) ($ultimo->contenido ?: '[adjunto]'), 0, 60) : '',
                'ultimo_mio'=> $ultimo ? ($ultimo->rol === MensajeWhatsapp::ROL_ASSISTANT) : false,
                'ultimo_at' => optional($c->ultimo_mensaje_at)->toIso8601String(),
            ];
        })->values()]);
    }

    public function mensajes(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $conv = ConversacionWhatsapp::with('cliente')->find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $msgs = $conv->mensajes()->orderBy('id')->limit(300)->get();
        try { $conv->update(['no_leidos' => 0]); } catch (\Throwable $e) {}

        return response()->json([
            'ok'       => true,
            'cliente'  => $conv->cliente->nombre ?? 'Cliente',
            'telefono' => $conv->telefono_visible ?? $conv->telefono_normalizado,
            'mensajes' => $msgs->map(function ($m) {
                $meta = $this->metaArr($m);
                return [
                    'id'        => $m->id,
                    'wamid'     => $m->mensaje_externo_id,
                    'contenido' => (string) ($m->contenido ?? ''),
                    'tipo'      => $m->tipo ?: 'text',
                    'media_url' => $meta['media_url'] ?? null,
                    'mio'       => in_array($m->rol, [MensajeWhatsapp::ROL_ASSISTANT], true),
                    'reaccion'  => $m->reaccion_operador ?: $m->reaccion_cliente,
                    'at'        => optional($m->created_at)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function enviar(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $r->validate(['texto' => 'required|string|max:4000']);
        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $texto  = trim($r->texto);
        $tenant = app(TenantManager::class)->current();
        $tel    = preg_replace('/\D+/', '', (string) $conv->telefono_normalizado);

        $ok = false;
        try { $ok = app(MetaWhatsappCloudService::class)->enviarTexto($tel, $texto, $tenant->id); }
        catch (\Throwable $e) { \Log::warning('Chat móvil enviar: ' . $e->getMessage()); }
        if (!$ok) return response()->json(['ok' => false, 'message' => 'No se pudo enviar. La ventana de 24h puede estar cerrada (usa una plantilla).'], 422);

        $m = app(ConversacionService::class)->agregarMensaje(
            $conv, MensajeWhatsapp::ROL_ASSISTANT, $texto,
            ['meta' => ['enviado_por_humano' => true, 'usuario_id' => $u->id, 'origen' => 'app_movil']]
        );
        try { $m->update(['ack' => MensajeWhatsapp::ACK_SENT]); } catch (\Throwable $e) {}

        return response()->json(['ok' => true, 'mensaje' => [
            'id' => $m->id, 'contenido' => $texto, 'tipo' => 'text', 'media_url' => null, 'mio' => true,
            'at' => now()->toIso8601String(),
        ]]);
    }

    /** Enviar foto / documento / audio (base64 data URL). */
    public function enviarMedia(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $r->validate([
            'data'     => 'required|string',
            'tipo'     => 'required|in:image,document,audio',
            'filename' => 'nullable|string',
            'caption'  => 'nullable|string|max:1000',
        ]);
        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        if (!preg_match('/^data:([^;]+);base64,(.+)$/i', $r->data, $mm)) {
            return response()->json(['ok' => false, 'message' => 'Formato de archivo no reconocido.'], 422);
        }
        $bytes = base64_decode($mm[2], true);
        if ($bytes === false || strlen($bytes) < 10) {
            return response()->json(['ok' => false, 'message' => 'Archivo inválido.'], 422);
        }
        if (strlen($bytes) > 90 * 1024 * 1024) {
            return response()->json(['ok' => false, 'message' => 'Archivo demasiado grande (máx 90 MB).'], 422);
        }

        $tenant   = app(TenantManager::class)->current();
        $slug     = $tenant?->slug ?: 'tenant';
        $nombre   = $r->filename ?: ('archivo_' . now()->timestamp);
        $safe     = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);
        $stored   = "tenants/{$slug}/chat/" . Str::uuid() . '_' . $safe;
        Storage::disk('public')->put($stored, $bytes);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($stored);

        $tel = preg_replace('/\D+/', '', (string) $conv->telefono_normalizado);
        $svc = app(MetaWhatsappCloudService::class);
        $caption = $r->caption ?: null;
        $ok = false;
        try {
            if ($r->tipo === 'image')      $ok = $svc->enviarImagen($tel, $mediaUrl, $caption, $tenant->id);
            elseif ($r->tipo === 'audio')  $ok = $svc->enviarAudio($tel, $mediaUrl, $tenant->id);
            else                           $ok = $svc->enviarDocumento($tel, $mediaUrl, $nombre, $caption, $tenant->id);
        } catch (\Throwable $e) { \Log::warning('Chat móvil media: ' . $e->getMessage()); }

        if (!$ok) return response()->json(['ok' => false, 'message' => 'No se pudo enviar el archivo (¿ventana 24h cerrada?).'], 422);

        $m = app(ConversacionService::class)->agregarMensaje(
            $conv, MensajeWhatsapp::ROL_ASSISTANT, (string) $caption,
            ['meta' => ['enviado_por_humano' => true, 'usuario_id' => $u->id, 'media_url' => $mediaUrl, 'caption' => $caption, 'origen' => 'app_movil']]
        );
        try { $m->update(['tipo' => $r->tipo, 'ack' => MensajeWhatsapp::ACK_SENT]); } catch (\Throwable $e) {}

        return response()->json(['ok' => true, 'mensaje' => [
            'id' => $m->id, 'contenido' => (string) $caption, 'tipo' => $r->tipo, 'media_url' => $mediaUrl, 'mio' => true,
            'at' => now()->toIso8601String(),
        ]]);
    }

    /** Plantillas Meta aprobadas (para enviar fuera de la ventana 24h). */
    public function plantillas(Request $r)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $ps = MetaWhatsappPlantilla::where('estado', 'aprobada')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'idioma', 'body_preview', 'num_variables', 'header_tipo']);
        return response()->json(['ok' => true, 'plantillas' => $ps]);
    }

    public function enviarPlantilla(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $r->validate(['nombre' => 'required|string', 'idioma' => 'nullable|string', 'variables' => 'array']);
        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $tenant = app(TenantManager::class)->current();
        $tel    = preg_replace('/\D+/', '', (string) $conv->telefono_normalizado);
        $vars   = array_values($r->input('variables', []));

        $ok = false;
        try {
            // El servicio persiste el mensaje saliente por sí mismo.
            $ok = app(MetaWhatsappCloudService::class)->enviarPlantilla($tel, $r->nombre, $vars, $tenant->id, $r->idioma ?: 'es');
        } catch (\Throwable $e) { \Log::warning('Chat móvil plantilla: ' . $e->getMessage()); }

        if (!$ok) return response()->json(['ok' => false, 'message' => 'No se pudo enviar la plantilla.'], 422);
        return response()->json(['ok' => true]);
    }

    /** Lista de respuestas rápidas activas del tenant. */
    public function respuestasRapidas(Request $r)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $rows = \App\Models\RespuestaRapida::where('activa', true)
            ->orderBy('orden')->get(['id', 'atajo', 'texto']);
        return response()->json(['ok' => true, 'respuestas' => $rows]);
    }

    /** Marcar/desmarcar como favorito (fijar). */
    public function favorito(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'No encontrada.'], 404);
        $valor = filter_var($r->input('valor', true), FILTER_VALIDATE_BOOLEAN);
        $conv->update(['fijada_at' => $valor ? now() : null]);
        return response()->json(['ok' => true, 'favorito' => $valor]);
    }

    /** Marcar/desmarcar como no leída. */
    public function noLeida(Request $r, int $id)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $conv = ConversacionWhatsapp::find($id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'No encontrada.'], 404);
        $valor = filter_var($r->input('valor', true), FILTER_VALIDATE_BOOLEAN);
        $data = ['marcada_no_leida' => $valor];
        if ($valor && (int) ($conv->no_leidos ?? 0) === 0) $data['no_leidos'] = 1;
        if (!$valor) $data['no_leidos'] = 0;
        $conv->update($data);
        return response()->json(['ok' => true, 'no_leida' => $valor]);
    }

    /** Reaccionar a un mensaje del cliente (emoji), como en WhatsApp. */
    public function reaccionar(Request $r, int $mid)
    {
        $u = $this->ctx($r);
        if (!$u->can('chat.usar')) return response()->json(['ok' => false, 'message' => 'Sin permiso.'], 403);
        $r->validate(['emoji' => 'nullable|string|max:8']);
        $m = MensajeWhatsapp::find($mid);
        if (!$m) return response()->json(['ok' => false, 'message' => 'Mensaje no encontrado.'], 404);
        $conv = ConversacionWhatsapp::find($m->conversacion_id);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Conversación no encontrada.'], 404);

        $emoji  = (string) $r->input('emoji', '');
        $tenant = app(TenantManager::class)->current();
        $tel    = preg_replace('/\D+/', '', (string) $conv->telefono_normalizado);

        // Enviar a Meta solo si el mensaje tiene wamid (es del cliente entrante)
        if ($m->mensaje_externo_id) {
            try { app(MetaWhatsappCloudService::class)->enviarReaccion($tel, $m->mensaje_externo_id, $emoji, $tenant->id); }
            catch (\Throwable $e) { \Log::warning('Reacción móvil: ' . $e->getMessage()); }
        }
        $m->update(['reaccion_operador' => $emoji ?: null, 'reaccion_operador_at' => $emoji ? now() : null]);
        return response()->json(['ok' => true, 'reaccion' => $emoji]);
    }
}
