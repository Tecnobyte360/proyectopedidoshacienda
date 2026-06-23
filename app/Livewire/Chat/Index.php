<?php

namespace App\Livewire\Chat;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\ConversacionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public ?int $conversacionActivaId = null;

    public string $nuevoMensaje = '';

    // 💬 Respondiendo a un mensaje específico (estilo WhatsApp reply)
    public ?int $respondiendoAMensajeId = null;
    public string $respondiendoAPreview = '';
    public string $respondiendoAAutor   = '';
    public string $busqueda     = '';
    public string $filtroEstado = 'todas';   // todas | activa | humano | bot | internos
    public string $filtroCanal  = 'todos';   // todos | whatsapp | instagram | widget

    // 👥 Grupos de clientes (estilo WhatsApp: filtrar chats por grupo)
    public bool   $mostrarGrupos    = false;  // despliega la fila de grupos
    public ?int   $grupoFiltroId    = null;   // grupo seleccionado (filtra chats)
    public string $nuevoGrupoNombre = '';     // crear grupo rápido

    // 👥 Modal "Crear lista" estilo WhatsApp (nombre + selección múltiple)
    public bool   $modalCrearGrupo     = false;
    public string $nombreListaNueva    = '';
    public string $buscarParaLista     = '';
    public array  $clientesSeleccionados = []; // [cliente_id => true]

    // 👥 Hilo UNIFICADO del grupo (el operador ve a todos en un solo chat)
    public ?int   $grupoAbiertoId  = null;
    public string $mensajeGrupo    = '';
    public ?int   $plantillaGrupoId = null; // enviar plantilla a todo el grupo
    public bool   $mostrarInternas = false;  // si es false, ocultas conversaciones internas

    // 📜 Scroll infinito: cuántas conversaciones mostrar. Arranca en 60 y crece
    //    de a 60 cada vez que el usuario llega al fondo de la lista.
    public int    $limiteConversaciones = 60;
    public bool   $hayMasConversaciones  = false;

    // Nueva conversación
    public bool   $nuevoChatModal   = false;
    public string $nuevoChatTel     = '';
    public string $nuevoChatNombre  = '';
    public string $nuevoChatMensaje = '';

    // 📋 Modal "Estado del pedido" — datos estructurados que tiene el bot
    public bool $pedidoEstadoModal = false;
    public string $pedidoEstadoTab = 'estado'; // 'estado' | 'prompt'

    // Modal "Publicar estado de WhatsApp" (POST /status de TecnoByteApp)
    public bool   $estadoModal       = false;
    public string $estadoCaption     = '';
    public string $estadoTab         = 'publicar';   // publicar | listar
    public array  $estadosPublicados = [];
    public bool   $cargandoEstados   = false;
    public ?string $estadosError     = null;

    // 🔄 Sincronización de historial de WhatsApp (por tenant)
    public ?array $resultadoSyncHistorial = null;

    // 🟢 Meta: estado de la ventana 24h para la conversación activa
    public bool   $ventana24hAbierta = true;
    public int    $ventana24hMinutosRestantes = 0;
    public bool   $tenantUsaMeta = false;

    // 🟢 Meta: envío de plantilla cuando ventana cerrada
    public ?int   $plantillaChatId = null;
    public array  $plantillaChatVars = [];

    /**
     * Permite abrir el chat directamente en una conversación específica
     * vía query string: /chat?conv=123. Útil para el banner de handoff en
     * /pedidos: cada botón "Atender" enlaza con su conv_id.
     */
    public function mount(\Illuminate\Http\Request $request): void
    {
        $convId = (int) $request->query('conv', 0);
        if ($convId <= 0) return;

        $conv = ConversacionWhatsapp::find($convId);
        if (!$conv) return;

        $this->seleccionar($conv->id);

        // Si la conversación está en modo humano, asegurarse de que el
        // operador la vea aunque tenga otro filtro activo.
        if ($conv->atendida_por_humano) {
            $this->filtroEstado = 'humano';
        }
    }

    /**
     * Sincroniza TODO el historial de WhatsApp del tenant actual.
     * Importa tickets, contactos y mensajes desde la API de TecnoByteApp.
     * Es seguro: aislado por tenant_id, no toca data de otros tenants.
     */
    public function sincronizarHistorial(): void
    {
        $tm = app(\App\Services\TenantManager::class);
        $tenantId = method_exists($tm, 'id') ? $tm->id() : null;

        if (!$tenantId) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No hay tenant activo. Contacta al admin.',
            ]);
            return;
        }

        $this->resultadoSyncHistorial = null;

        try {
            $stats = app(\App\Services\WhatsappContactosService::class)
                ->sincronizarHistorialCompleto();
            $this->resultadoSyncHistorial = $stats;

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✓ Historial sincronizado: {$stats['tickets_procesados']} chats, "
                           . "{$stats['clientes_creados']} clientes nuevos, "
                           . "{$stats['mensajes_imp']} mensajes importados.",
            ]);
        } catch (\Throwable $e) {
            $this->resultadoSyncHistorial = ['error' => $e->getMessage()];
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error al sincronizar: ' . $e->getMessage(),
            ]);
        }
    }

    public function abrirEstadoModal(): void
    {
        $this->estadoModal       = true;
        $this->estadoCaption     = '';
        $this->estadoTab         = 'publicar';
        $this->estadosError      = null;
    }

    /**
     * 📋 Abrir modal con el estado estructurado del pedido en BD.
     * Muestra qué datos tiene el bot recolectados para la conversación
     * activa (productos, dirección, cédula, sede, etc.).
     */
    public function abrirPedidoEstadoModal(): void
    {
        if (!$this->conversacionActivaId) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Selecciona una conversación primero.']);
            return;
        }
        $this->pedidoEstadoModal = true;
        $this->pedidoEstadoTab = 'estado';
    }

    public function cerrarPedidoEstadoModal(): void
    {
        $this->pedidoEstadoModal = false;
    }

    public function cambiarTab(string $tab): void
    {
        if (!in_array($tab, ['estado', 'prompt'], true)) return;
        $this->pedidoEstadoTab = $tab;
    }

    /**
     * 🔄 Resetear el estado del pedido — el bot empieza limpio en su próxima respuesta.
     */
    public function resetearEstadoPedido(): void
    {
        if (!$this->conversacionActivaId) return;

        $conv = \App\Models\ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) return;

        try {
            app(\App\Services\EstadoPedidoService::class)
                ->resetear($conv, 'reset_manual_admin_modal');
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => '🔄 Estado del pedido reseteado. El bot empezará limpio en la próxima respuesta.',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function cerrarEstadoModal(): void
    {
        $this->estadoModal   = false;
        $this->estadoCaption = '';
    }

    public function cambiarTabEstado(string $tab): void
    {
        $this->estadoTab = $tab;
        if ($tab === 'listar') {
            $this->cargarEstadosPublicados();
        }
    }

    /**
     * GET /status — lista los estados publicados en el WhatsApp del tenant.
     */
    public function cargarEstadosPublicados(): void
    {
        $this->cargandoEstados = true;
        $this->estadosError    = null;
        $this->estadosPublicados = [];

        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                $this->estadosError = 'Sin token de WhatsApp para este tenant.';
                return;
            }

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred     = $resolver->credenciales();
            $endpoint = rtrim($cred['api_base_url'], '/') . '/status';

            $connId = $this->resolverConnectionId();
            $query  = $connId ? ['whatsappId' => $connId] : [];

            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->get($endpoint, $query);

            if ($resp->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $resp = Http::withoutVerifying()
                        ->withToken($newToken)
                        ->timeout(20)
                        ->get($endpoint, $query);
                }
            }

            if (!$resp->successful()) {
                $this->estadosError = "API ({$resp->status()}): " . mb_substr($resp->body(), 0, 200);
                Log::warning('GET /status falló', ['status' => $resp->status(), 'body' => $resp->body()]);
                return;
            }

            $data = $resp->json();
            // La API devuelve un array directo de estados.
            $items = is_array($data) && isset($data[0]) ? $data : ($data['data'] ?? $data['statuses'] ?? []);
            $base  = rtrim($cred['api_base_url'], '/');

            $this->estadosPublicados = collect($items)->map(function ($it) {
                $rel = (string) ($it['mediaUrl'] ?? $it['url'] ?? '');
                // Si ya es URL absoluta y pública, la usamos. Si es solo el nombre
                // del archivo, la pasamos por el proxy autenticado.
                $abs = preg_match('#^https?://#', $rel)
                    ? $rel
                    : ($rel ? route('whatsapp-status.media', ['filename' => basename($rel)]) : null);

                return [
                    'id'         => $it['id'] ?? null,
                    'caption'    => trim((string) ($it['body'] ?? $it['caption'] ?? '')),
                    'media_url'  => $abs,
                    'media_type' => (string) ($it['mediaType'] ?? $it['type'] ?? ''),
                    'es_video'   => str_starts_with((string) ($it['mediaType'] ?? ''), 'video/'),
                    'expires_at' => $it['expiresAt'] ?? null,
                    'created_at' => $it['createdAt'] ?? null,
                    'phone'      => $it['whatsapp']['phoneNumber'] ?? '',
                    'wa_name'    => $it['whatsapp']['name'] ?? '',
                ];
            })->sortByDesc('created_at')->values()->all();
        } catch (\Throwable $e) {
            Log::error('Excepción listando estados WhatsApp: ' . $e->getMessage());
            $this->estadosError = 'Error: ' . $e->getMessage();
        } finally {
            $this->cargandoEstados = false;
        }
    }

    /**
     * Publica un estado en WhatsApp (Status/Stories) usando el endpoint
     * POST /status de TecnoByteApp. Recibe la media como data URL base64
     * (igual que enviarImagen) para evitar el upload-file 401 de Livewire.
     */
    public function publicarEstado(string $dataUrl, string $caption = ''): void
    {
        if (!str_starts_with($dataUrl, 'data:')) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de archivo inválido.']);
            return;
        }

        if (!preg_match('#^data:([^;]+);base64,(.+)$#', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo leer el archivo.']);
            return;
        }

        $mime  = $m[1];
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Archivo vacío o corrupto.']);
            return;
        }

        // Tamaño máximo razonable: 16MB (límite común de WhatsApp)
        if (strlen($bytes) > 16 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El archivo supera 16MB.']);
            return;
        }

        $ext = match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png')   => 'png',
            str_contains($mime, 'webp')  => 'webp',
            str_contains($mime, 'mp4')   => 'mp4',
            str_contains($mime, 'video') => 'mp4',
            default                      => 'bin',
        };
        $filename = 'status-' . now()->timestamp . '.' . $ext;

        $caption = trim($caption !== '' ? $caption : $this->estadoCaption);

        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred     = $resolver->credenciales();
        $endpoint = rtrim($cred['api_base_url'], '/') . '/status';

        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Sin token de WhatsApp para este tenant.']);
                return;
            }

            $payload = [];
            if ($caption !== '') $payload['caption'] = $caption;

            // Algunos endpoints de TecnoByteApp requieren whatsappId
            $connId = $this->resolverConnectionId();
            if ($connId) $payload['whatsappId'] = $connId;

            // El endpoint /status de TecnoByteApp suele aceptar el archivo como
            // "medias" (con s). Algunos despliegues usan "media" o "file".
            // Probamos en orden y nos quedamos con el que NO devuelva 400.
            $intentos = ['medias', 'media', 'file'];
            $response = null;
            $campoUsado = null;

            $hacerLlamada = function (string $useToken, string $campo) use ($endpoint, $bytes, $filename, $payload) {
                return Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->attach($campo, $bytes, $filename)
                    ->post($endpoint, $payload);
            };

            foreach ($intentos as $campo) {
                $resp = $hacerLlamada($token, $campo);

                if ($resp->status() === 401) {
                    Cache::forget($resolver->tokenCacheKey());
                    $newToken = $this->obtenerTokenWhatsapp();
                    if ($newToken) {
                        $token = $newToken;
                        $resp  = $hacerLlamada($newToken, $campo);
                    }
                }

                Log::info('Status WhatsApp intento', [
                    'campo'  => $campo,
                    'status' => $resp->status(),
                    'body'   => mb_substr((string) $resp->body(), 0, 500),
                ]);

                if ($resp->successful()) {
                    $response   = $resp;
                    $campoUsado = $campo;
                    break;
                }

                // Si el error NO es 400, no tiene sentido intentar con otro campo
                if ($resp->status() !== 400) {
                    $response = $resp;
                    break;
                }

                $response = $resp;
            }

            if (!$response || !$response->successful()) {
                Log::warning('Publicar estado WhatsApp falló', [
                    'status'   => $response?->status(),
                    'body'     => $response?->body(),
                    'endpoint' => $endpoint,
                    'mime'     => $mime,
                    'filename' => $filename,
                    'caption_len' => strlen($caption),
                    'intentos' => $intentos,
                ]);
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => '❌ La API rechazó el estado (' . ($response?->status() ?: 'sin respuesta') . '). Revisa los logs.',
                ]);
                return;
            }

            Log::info('✅ Estado WhatsApp publicado', ['campo_usado' => $campoUsado, 'endpoint' => $endpoint]);

            $this->cerrarEstadoModal();
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => '✅ Estado publicado en WhatsApp.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Excepción publicando estado WhatsApp: ' . $e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /** 📜 Scroll infinito: carga el siguiente bloque de conversaciones. */
    public function cargarMasConversaciones(): void
    {
        if (!$this->hayMasConversaciones) return;
        $this->limiteConversaciones += 60;
    }

    /** Al buscar, volvemos al primer bloque (la búsqueda recorre toda la BD). */
    public function updatedBusqueda(): void
    {
        $this->limiteConversaciones = 60;
    }

    /** Al cambiar el filtro de estado, reiniciamos el scroll infinito. */
    public function updatedFiltroEstado(): void
    {
        $this->limiteConversaciones = 60;
        // Salir del modo grupos al cambiar de filtro (cambio rápido).
        $this->mostrarGrupos  = false;
        $this->grupoAbiertoId = null;
        $this->grupoFiltroId  = null;
    }

    /** Al cambiar el filtro de canal, reiniciamos el scroll infinito. */
    public function updatedFiltroCanal(): void
    {
        $this->limiteConversaciones = 60;
    }

    public function seleccionar(int $id): void
    {
        // 🛡️ Validar que el usuario PUEDE ver esta conversación (filtro por departamento).
        // Previene acceso directo por URL a conversaciones de otros departamentos.
        $user = auth()->user();
        if ($user && !$user->puedeVerTodasLasConversaciones()) {
            $deptoIds = $user->departamentos()->pluck('departamentos.id')->all();
            $conv = ConversacionWhatsapp::select('id', 'departamento_id')->find($id);

            if (!$conv) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
                return;
            }

            // Si la conversación está derivada a un depto que NO es del agente → bloquear
            if ($conv->departamento_id && !in_array($conv->departamento_id, $deptoIds, true)) {
                Log::warning('🛡️ Intento de acceso cross-depto bloqueado', [
                    'user_id'         => $user->id,
                    'conv_id'         => $id,
                    'depto_conv'      => $conv->departamento_id,
                    'deptos_usuario'  => $deptoIds,
                ]);
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => '⛔ Esta conversación pertenece a otro departamento.',
                ]);
                return;
            }

            // Si la conversación no está derivada y el agente tiene depto(s) asignado(s) → bloquear
            if (is_null($conv->departamento_id) && !empty($deptoIds)) {
                $this->dispatch('notify', [
                    'type'    => 'warning',
                    'message' => 'Esta conversación aún no ha sido derivada a tu departamento.',
                ]);
                return;
            }
        }

        $this->conversacionActivaId = $id;
        $this->nuevoMensaje = '';
        $this->plantillaChatId = null;
        $this->plantillaChatVars = [];

        // 🧹 Limpiar estado de "respondiendo a un mensaje" si veníamos de otro chat
        $this->cancelarRespuesta();

        // 📭 Al abrir la conversación: resetear no_leidos y limpiar el flag
        // manual "marcada_no_leida" si estaba prendido.
        try {
            ConversacionWhatsapp::where('id', $id)
                ->where(fn ($q) => $q->where('no_leidos', '>', 0)->orWhere('marcada_no_leida', true))
                ->update([
                    'no_leidos'        => 0,
                    'marcada_no_leida' => false,
                    'ultima_vista_at'  => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo limpiar no_leidos al abrir conv: ' . $e->getMessage());
        }

        // 🟢 Meta: calcular estado de ventana 24h para esta conversación
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            $this->tenantUsaMeta = $tenant
                && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;

            if ($this->tenantUsaMeta) {
                $convFull = ConversacionWhatsapp::find($id);
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                $this->ventana24hAbierta = $convFull ? $checker->abierta($convFull) : false;
                $this->ventana24hMinutosRestantes = $convFull ? $checker->minutosRestantes($convFull) : 0;
            } else {
                $this->ventana24hAbierta = true;
                $this->ventana24hMinutosRestantes = 0;
            }
        } catch (\Throwable $e) {
            Log::warning('Cálculo ventana 24h falló: ' . $e->getMessage());
            $this->ventana24hAbierta = true;
            $this->tenantUsaMeta = false;
        }

        // Marcar como leída
        ConversacionWhatsapp::where('id', $id)->update([
            'no_leidos'       => 0,
            'ultima_vista_at' => now(),
        ]);

        $this->dispatch('chat-cambiado', conversacionId: $id);
    }

    #[On('refrescar-chat')]
    public function refrescar(): void
    {
        // Solo dispara render
    }

    /**
     * 🟢 Auto-rellena las variables de la plantilla con datos del contexto
     * (nombre cliente, nombre negocio, número de pedido, total, etc.) cuando
     * el operador selecciona una plantilla. El operador puede editar luego.
     *
     * Heurística simple basada en el nombre de la plantilla:
     *  - bienvenida_*       → var1=nombreCliente, var2=nombreNegocio
     *  - pedido_*           → var1=nombreCliente, var2=#pedido, var3=total, var4=domiciliario
     *  - felicitacion_*     → var1=nombreCliente, var2=descuento, var3=fechaVencimiento
     *  - recordatorio_pago  → var1=nombreCliente, var2=#pedido, var3=monto
     *  - promocion_general  → var1=nombreCliente, var2=oferta, var3=link
     *  - encuesta_*         → var1=nombreCliente, var2=#pedido
     */
    public function updatedPlantillaChatId(): void
    {
        $this->plantillaChatVars = [];
        if (!$this->plantillaChatId) return;

        try {
            $tpl = \App\Models\MetaWhatsappPlantilla::find($this->plantillaChatId);
            if (!$tpl || $tpl->num_variables < 1) return;

            // Contexto: cliente actual + negocio
            $conv = $this->conversacionActivaId
                ? \App\Models\ConversacionWhatsapp::with(['cliente', 'pedido'])->find($this->conversacionActivaId)
                : null;

            $nombreCliente = trim(($conv?->cliente?->nombre ?: '') ?: 'Cliente');
            // Solo primer nombre (más natural en saludos)
            $primerNombre = explode(' ', $nombreCliente)[0];

            $tenant = app(\App\Services\TenantManager::class)->current();
            $nombreNegocio = $tenant?->nombre ?: 'nuestro negocio';

            $pedido = $conv?->pedido;
            $numPedido = $pedido?->id ?? '';
            $totalPedido = $pedido ? '$' . number_format($pedido->total, 0, ',', '.') : '';

            $nombre = strtolower($tpl->nombre);

            // 🎯 Heurística por nombre de plantilla
            if (str_starts_with($nombre, 'bienvenida')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => $nombreNegocio];
            } elseif (str_starts_with($nombre, 'pedido_confirmado')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido, 3 => $totalPedido];
            } elseif (str_starts_with($nombre, 'pedido_en_proceso')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido];
            } elseif (str_starts_with($nombre, 'pedido_en_camino')) {
                $domNombre = $pedido?->domiciliario?->nombre ?: '';
                $tiempoEst = $pedido?->tiempo_estimado_min ? $pedido->tiempo_estimado_min . ' min' : '20 min';
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido, 3 => $domNombre, 4 => $tiempoEst];
            } elseif (str_starts_with($nombre, 'pedido_entregado')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido];
            } elseif (str_starts_with($nombre, 'pedido_cancelado')) {
                $motivo = $pedido?->motivo_cancelacion ?: 'No se pudo procesar';
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido, 3 => $motivo];
            } elseif (str_starts_with($nombre, 'encuesta')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido];
            } elseif (str_starts_with($nombre, 'felicitacion')) {
                $vencimiento = now()->addDays(30)->format('d/m/Y');
                $this->plantillaChatVars = [1 => $primerNombre, 2 => '30%', 3 => $vencimiento];
            } elseif (str_starts_with($nombre, 'recordatorio_pago')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => (string) $numPedido, 3 => $totalPedido];
            } elseif (str_starts_with($nombre, 'promocion')) {
                $this->plantillaChatVars = [1 => $primerNombre, 2 => '20% de descuento', 3 => ''];
            } else {
                // Genérico: var1 = nombre cliente, var2 = nombre negocio, resto vacíos
                $this->plantillaChatVars = [1 => $primerNombre, 2 => $nombreNegocio];
            }

            // Rellenar las que falten con vacío (para que Livewire las trackee)
            for ($i = 1; $i <= $tpl->num_variables; $i++) {
                if (!isset($this->plantillaChatVars[$i])) {
                    $this->plantillaChatVars[$i] = '';
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo auto-rellenar plantilla: ' . $e->getMessage());
        }
    }

    public function tomarControl(): void
    {
        if (!$this->conversacionActivaId) return;

        ConversacionWhatsapp::find($this->conversacionActivaId)
            ?->update(['atendida_por_humano' => true]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✋ Tomaste el control de la conversación. El bot ya no responde.',
        ]);
    }

    /**
     * 📌 Fijar / desfijar una conversación en la lista.
     * Las fijadas aparecen siempre arriba, ordenadas por cuándo se fijaron.
     */
    /* ───────── 👥 GRUPOS de clientes (estilo WhatsApp) ───────── */

    /** Muestra/oculta la fila de grupos. */
    public function toggleMostrarGrupos(): void
    {
        $this->mostrarGrupos = !$this->mostrarGrupos;
        if (!$this->mostrarGrupos) $this->grupoFiltroId = null;
    }

    /** Selecciona/deselecciona un grupo para filtrar las conversaciones. */
    public function filtrarPorGrupo(?int $grupoId): void
    {
        $this->grupoFiltroId = ($this->grupoFiltroId === $grupoId) ? null : $grupoId;
    }

    /** Crea un grupo nuevo desde el chat (rápido, solo nombre). */
    public function crearGrupoRapido(): void
    {
        $nombre = trim($this->nuevoGrupoNombre);
        if ($nombre === '') return;
        $g = \App\Models\GrupoCliente::create([
            'tenant_id' => app(\App\Services\TenantManager::class)->id(),
            'nombre'    => $nombre,
            'color'     => '#d68643',
        ]);
        $this->nuevoGrupoNombre = '';
        $this->grupoFiltroId = $g->id;
        $this->dispatch('notify', ['type' => 'success', 'message' => "Grupo '{$nombre}' creado."]);
    }

    /* ─── Modal "Crear lista" estilo WhatsApp (nombre + multi-selección) ─── */

    public function abrirModalCrearGrupo(): void
    {
        $this->reset(['nombreListaNueva', 'buscarParaLista', 'clientesSeleccionados']);
        $this->modalCrearGrupo = true;
    }

    public function cerrarModalCrearGrupo(): void
    {
        $this->modalCrearGrupo = false;
    }

    /** Marca/desmarca un cliente en la selección múltiple. */
    public function toggleClienteLista(int $clienteId): void
    {
        if (isset($this->clientesSeleccionados[$clienteId])) {
            unset($this->clientesSeleccionados[$clienteId]);
        } else {
            $this->clientesSeleccionados[$clienteId] = true;
        }
    }

    /** Crea la lista/grupo con TODOS los clientes seleccionados de una. */
    public function crearListaConMiembros(): void
    {
        $nombre = trim($this->nombreListaNueva);
        if ($nombre === '') {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Ponle un nombre a la lista.']);
            return;
        }
        $g = \App\Models\GrupoCliente::create([
            'tenant_id' => app(\App\Services\TenantManager::class)->id(),
            'nombre'    => $nombre,
            'color'     => '#d68643',
        ]);

        $ids = array_keys(array_filter($this->clientesSeleccionados));
        if (!empty($ids)) {
            $g->clientes()->syncWithoutDetaching($ids);
        }

        $this->modalCrearGrupo = false;
        $this->mostrarGrupos = true;
        $this->grupoFiltroId = $g->id;
        $this->reset(['nombreListaNueva', 'buscarParaLista', 'clientesSeleccionados']);
        $this->dispatch('notify', ['type' => 'success', 'message' => "Lista '{$nombre}' creada con " . count($ids) . " miembro(s)."]);
    }

    /* ─── 👥 Hilo UNIFICADO del grupo ─── */

    /** Abre el hilo unificado del grupo (todos los miembros en un chat). */
    public function abrirGrupoUnificado(int $grupoId): void
    {
        $this->grupoAbiertoId = $grupoId;
        $this->conversacionActivaId = null; // salir de cualquier conversación 1-a-1

        // 🟢 Asegurar que se carguen las plantillas Meta (para el panel del grupo).
        $tenant = app(\App\Services\TenantManager::class)->current();
        $this->tenantUsaMeta = $tenant
            && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;
    }

    public function cerrarGrupoUnificado(): void
    {
        $this->grupoAbiertoId = null;
        $this->mensajeGrupo = '';
    }

    /** 📌 Fijar / desfijar el grupo (aparece primero en la lista). */
    public function toggleFijarGrupo(int $grupoId): void
    {
        $g = \App\Models\GrupoCliente::find($grupoId);
        if (!$g) return;
        $g->update(['fijado_at' => $g->fijado_at ? null : now()]);
        $this->dispatch('notify', ['type' => 'info', 'message' => $g->fijado_at ? "📌 '{$g->nombre}' fijado" : "Grupo desfijado"]);
    }

    /** ✏️ Renombrar el grupo. */
    public string $renombrarGrupoNombre = '';
    public ?int   $renombrandoGrupoId   = null;

    public function abrirRenombrarGrupo(int $grupoId): void
    {
        $g = \App\Models\GrupoCliente::find($grupoId);
        if (!$g) return;
        $this->renombrandoGrupoId   = $g->id;
        $this->renombrarGrupoNombre = $g->nombre;
    }

    public function guardarNombreGrupo(): void
    {
        $nombre = trim($this->renombrarGrupoNombre);
        if ($nombre === '' || !$this->renombrandoGrupoId) return;
        \App\Models\GrupoCliente::where('id', $this->renombrandoGrupoId)->update(['nombre' => $nombre]);
        $this->renombrandoGrupoId = null;
        $this->renombrarGrupoNombre = '';
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Nombre actualizado.']);
    }

    /** 🗑️ Eliminar el grupo. */
    public function eliminarGrupoChat(int $grupoId): void
    {
        \App\Models\GrupoCliente::where('id', $grupoId)->delete();
        if ($this->grupoAbiertoId === $grupoId) $this->grupoAbiertoId = null;
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Grupo eliminado.']);
    }

    /** Grupo abierto (con clientes). */
    public function getGrupoActivoProperty()
    {
        if (!$this->grupoAbiertoId) return null;
        return \App\Models\GrupoCliente::with(['clientes' => fn ($q) => $q->whereNotNull('telefono_normalizado')])
            ->find($this->grupoAbiertoId);
    }

    /**
     * Mensajes del HILO DEL GRUPO únicamente (no el historial de las convs
     * normales). Incluye: lo que el operador envió AL grupo (meta.grupo_id)
     * + las respuestas de cada miembro a partir de su primer mensaje de grupo.
     */
    public function getMensajesGrupoProperty()
    {
        $grupo = $this->grupoActivo;
        if (!$grupo) return collect();

        $clienteIds = $grupo->clientes->pluck('id')->all();
        if (empty($clienteIds)) return collect();

        $convs = ConversacionWhatsapp::whereIn('cliente_id', $clienteIds)
            ->with('cliente:id,nombre,telefono_normalizado,foto_url')
            ->get();
        $convIds = $convs->pluck('id')->all();
        if (empty($convIds)) return collect();

        $convCliente = $convs->keyBy('id');

        // 1) Primer mensaje de ESTE grupo por conversación (cuándo arrancó el hilo).
        $primerGrupoPorConv = MensajeWhatsapp::whereIn('conversacion_id', $convIds)
            ->where('meta->grupo_id', $this->grupoAbiertoId)
            ->selectRaw('conversacion_id, MIN(id) as min_id')
            ->groupBy('conversacion_id')
            ->pluck('min_id', 'conversacion_id');

        if ($primerGrupoPorConv->isEmpty()) return collect(); // aún no se habló por el grupo

        // 2) Traer: salientes del grupo (meta.grupo_id) + entrantes del cliente
        //    posteriores a su primer mensaje de grupo (sus respuestas al grupo).
        $mensajes = MensajeWhatsapp::whereIn('conversacion_id', $convIds)
            ->whereIn('rol', [MensajeWhatsapp::ROL_USER, MensajeWhatsapp::ROL_ASSISTANT])
            ->where(function ($q) use ($primerGrupoPorConv) {
                $q->where('meta->grupo_id', $this->grupoAbiertoId);
                foreach ($primerGrupoPorConv as $convId => $minId) {
                    $q->orWhere(function ($qq) use ($convId, $minId) {
                        $qq->where('conversacion_id', $convId)
                           ->where('id', '>=', $minId)
                           ->where('rol', MensajeWhatsapp::ROL_USER);
                    });
                }
            })
            ->orderBy('id')
            ->limit(400)
            ->get();

        // 3) Consolidar: los salientes del MISMO lote (grupo_batch) = 1 burbuja.
        //    Mostramos solo el primero de cada lote + cuántos lo recibieron.
        $vistos = [];
        return $mensajes->filter(function ($m) use (&$vistos) {
            if ($m->rol === MensajeWhatsapp::ROL_ASSISTANT) {
                $batch = $m->meta['grupo_batch'] ?? null;
                if ($batch) {
                    if (isset($vistos[$batch])) return false; // ya mostramos este lote
                    $vistos[$batch] = true;
                }
            }
            return true;
        })->map(function ($m) use ($convCliente, $mensajes) {
            $cli = $convCliente->get($m->conversacion_id)?->cliente;
            $m->_remitente = $cli?->nombre ?: ($cli?->telefono_normalizado ?? 'Cliente');
            $m->_foto      = $cli?->foto_url;
            // Cuántos miembros recibieron este lote (para el badge).
            $batch = $m->meta['grupo_batch'] ?? null;
            $m->_destinatarios = $batch
                ? $mensajes->where('meta.grupo_batch', $batch)->count()
                : 1;
            return $m;
        })->values();
    }

    /** Envía el mensaje a TODOS los miembros del grupo (cada uno en su privado). */
    public function enviarAGrupo(): void
    {
        $texto = trim($this->mensajeGrupo);
        $grupo = $this->grupoActivo;
        if ($texto === '' || !$grupo) return;

        $tenant = app(\App\Services\TenantManager::class)->current();
        $esMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;
        $svc      = app(\App\Services\Meta\MetaWhatsappCloudService::class);
        $checker  = app(\App\Services\Whatsapp\Ventana24hChecker::class);
        $convSvc  = app(ConversacionService::class);
        $batch    = (string) \Illuminate\Support\Str::uuid(); // 🔗 mismo lote = 1 burbuja

        $enviados = 0; $omitidos = 0;
        foreach ($grupo->clientes as $cli) {
            $tel = $cli->telefono_normalizado;
            if (!$tel) { $omitidos++; continue; }

            // Conversación del cliente (crear si no existe).
            $conv = ConversacionWhatsapp::firstOrCreate(
                ['telefono_normalizado' => $tel],
                ['tenant_id' => $tenant?->id, 'cliente_id' => $cli->id, 'estado' => 'activa', 'canal' => 'whatsapp']
            );

            $ok = false;
            if ($esMeta) {
                // Solo texto libre si la ventana 24h está abierta.
                try { $abierta = $checker->abierta($conv); } catch (\Throwable $e) { $abierta = false; }
                if ($abierta) {
                    $ok = $svc->enviarTexto($tel, $texto, $tenant->id);
                }
            }

            // Registrar en la conversación del cliente (trazabilidad) si se envió.
            if ($ok) {
                $convSvc->agregarMensaje($conv, MensajeWhatsapp::ROL_ASSISTANT, $texto, [
                    'meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id(), 'grupo_id' => $grupo->id, 'grupo_batch' => $batch],
                ]);
                $enviados++;
            } else {
                $omitidos++;
            }
        }

        $this->mensajeGrupo = '';
        $msg = "Enviado a {$enviados} miembro(s).";
        if ($omitidos > 0) $msg .= " {$omitidos} sin ventana de 24h abierta — usá una plantilla para llegarles.";
        $this->dispatch('notify', ['type' => $enviados > 0 ? 'success' : 'warning', 'message' => $msg]);
    }

    /**
     * 📋 Envía una PLANTILLA Meta a TODOS los miembros del grupo.
     * Funciona AUNQUE no tengan la ventana de 24h abierta (lo que permite
     * llegarle a todos siempre). Cada uno la recibe en su privado.
     */
    public function enviarPlantillaAGrupo(): void
    {
        $grupo = $this->grupoActivo;
        if (!$grupo || !$this->plantillaGrupoId) return;

        $tpl = \App\Models\MetaWhatsappPlantilla::find($this->plantillaGrupoId);
        if (!$tpl) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Plantilla no encontrada.']);
            return;
        }

        $tenant  = app(\App\Services\TenantManager::class)->current();
        $svc     = app(\App\Services\Meta\MetaWhatsappCloudService::class);
        $convSvc = app(ConversacionService::class);
        $numVars = (int) ($tpl->num_variables ?? 0);
        $nombreNegocio = $tenant?->nombre ?: 'nosotros';
        $batch   = (string) \Illuminate\Support\Str::uuid(); // 🔗 mismo lote = 1 burbuja

        $enviados = 0; $fallidos = 0;
        foreach ($grupo->clientes as $cli) {
            $tel = $cli->telefono_normalizado;
            if (!$tel) { $fallidos++; continue; }

            // Variables comunes: 1=primer nombre del cliente, 2=negocio.
            $primerNombre = trim(explode(' ', (string) ($cli->nombre ?: 'Cliente'))[0]);
            $vars = [];
            for ($i = 1; $i <= $numVars; $i++) {
                $vars[] = $i === 1 ? $primerNombre : ($i === 2 ? $nombreNegocio : '');
            }

            $ok = $svc->enviarPlantilla($tel, $tpl->nombre, $vars, $tenant?->id, $tpl->idioma ?: 'es');

            if ($ok) {
                $conv = ConversacionWhatsapp::firstOrCreate(
                    ['telefono_normalizado' => $tel],
                    ['tenant_id' => $tenant?->id, 'cliente_id' => $cli->id, 'estado' => 'activa', 'canal' => 'whatsapp']
                );
                $body = $tpl->body_preview ?: ('[plantilla:' . $tpl->nombre . ']');
                foreach ($vars as $i => $v) {
                    $body = str_replace(['{{' . ($i + 1) . '}}', '{{ ' . ($i + 1) . ' }}'], (string) $v, $body);
                }
                $convSvc->agregarMensaje($conv, MensajeWhatsapp::ROL_ASSISTANT, $body, [
                    'meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id(), 'grupo_id' => $grupo->id, 'grupo_batch' => $batch, 'plantilla' => $tpl->nombre],
                ]);
                $enviados++;
            } else {
                $fallidos++;
            }
        }

        $this->plantillaGrupoId = null;
        $msg = "Plantilla enviada a {$enviados} miembro(s).";
        if ($fallidos > 0) $msg .= " {$fallidos} fallaron.";
        $this->dispatch('notify', ['type' => $enviados > 0 ? 'success' : 'warning', 'message' => $msg]);
    }

    /** 🖼️ Broadcast de imagen a todos los miembros del grupo (ventana 24h). */
    private function broadcastImagenAGrupo(string $dataUrl, string $caption): void
    {
        $grupo = $this->grupoActivo;
        if (!$grupo) return;
        if (!preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de imagen no reconocido.']);
            return;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 100) return;
        $ext = str_contains($m[1], 'png') ? 'png' : (str_contains($m[1], 'webp') ? 'webp' : (str_contains($m[1], 'gif') ? 'gif' : 'jpg'));

        // Subir UNA vez → URL pública.
        $filename = 'imagenes-out/grp_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($filename, $bytes);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);
        $caption  = trim($caption);

        $this->broadcastMediaAGrupo($grupo, 'image', $mediaUrl, $caption,
            fn ($svc, $tel, $tid) => $svc->enviarImagen($tel, $mediaUrl, $caption ?: null, $tid),
            $caption !== '' ? $caption : '🖼️ Imagen');
    }

    /** 🎤 Broadcast de audio a todos los miembros del grupo (ventana 24h). */
    private function broadcastAudioAGrupo(string $dataUrl): void
    {
        $grupo = $this->grupoActivo;
        if (!$grupo) return;
        if (!preg_match('/^data:(audio\/[^;,]+(?:;[^,]*)?);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de audio no reconocido.']);
            return;
        }
        $mime  = strtolower(explode(';', $m[1])[0]);
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 100) return;
        $ext = str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mpeg') ? 'mp3' : (str_contains($mime, 'mp4') ? 'm4a' : 'webm'));

        [$bytesEnvio, $extEnvio] = $this->convertirAOggOpus($bytes, $ext);
        $filename = 'audios-out/grp_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extEnvio;
        Storage::disk('public')->put($filename, $bytesEnvio);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        $this->broadcastMediaAGrupo($grupo, 'audio', $mediaUrl, '',
            fn ($svc, $tel, $tid) => $svc->enviarAudio($tel, $mediaUrl, $tid),
            '🎤 Nota de voz');
    }

    /** Núcleo del broadcast de media: envía a cada miembro con ventana abierta. */
    private function broadcastMediaAGrupo($grupo, string $tipo, string $mediaUrl, string $caption, callable $enviar, string $textoRegistro): void
    {
        $tenant  = app(\App\Services\TenantManager::class)->current();
        $esMeta  = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;
        $svc     = app(\App\Services\Meta\MetaWhatsappCloudService::class);
        $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
        $convSvc = app(ConversacionService::class);
        $batch   = (string) \Illuminate\Support\Str::uuid();

        $enviados = 0; $omitidos = 0;
        foreach ($grupo->clientes as $cli) {
            $tel = $cli->telefono_normalizado;
            if (!$tel) { $omitidos++; continue; }
            $conv = ConversacionWhatsapp::firstOrCreate(
                ['telefono_normalizado' => $tel],
                ['tenant_id' => $tenant?->id, 'cliente_id' => $cli->id, 'estado' => 'activa', 'canal' => 'whatsapp']
            );
            $ok = false;
            if ($esMeta) {
                try { $abierta = $checker->abierta($conv); } catch (\Throwable $e) { $abierta = false; }
                if ($abierta) $ok = (bool) $enviar($svc, $tel, $tenant->id);
            }
            if ($ok) {
                $convSvc->agregarMensaje($conv, MensajeWhatsapp::ROL_ASSISTANT, $textoRegistro, [
                    'tipo' => $tipo,
                    'meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id(), 'media_url' => $mediaUrl, 'caption' => $caption, 'grupo_id' => $grupo->id, 'grupo_batch' => $batch, 'provider' => 'meta'],
                ]);
                $enviados++;
            } else { $omitidos++; }
        }

        $this->dispatch('mensaje-enviado');
        $msg = ucfirst($tipo === 'audio' ? 'audio' : 'imagen') . " enviada a {$enviados} miembro(s).";
        if ($omitidos > 0) $msg .= " {$omitidos} sin ventana de 24h.";
        $this->dispatch('notify', ['type' => $enviados > 0 ? 'success' : 'warning', 'message' => $msg]);
    }

    /** Clientes para el modal: chats recientes o resultado de búsqueda. */
    public function getClientesParaListaProperty()
    {
        $q = trim($this->buscarParaLista);
        $base = \App\Models\Cliente::whereNotNull('telefono_normalizado');

        if (mb_strlen($q) >= 2) {
            $base->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('telefono_normalizado', 'like', "%{$q}%")
                  ->orWhere('cedula', 'like', "%{$q}%");
            });
            return $base->limit(30)->get();
        }

        // Sin búsqueda: chats recientes (clientes con conversación reciente).
        $idsRecientes = ConversacionWhatsapp::query()
            ->whereNotNull('cliente_id')
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(30)
            ->pluck('cliente_id')->unique()->values()->all();

        if (empty($idsRecientes)) return $base->limit(20)->get();

        return \App\Models\Cliente::whereIn('id', $idsRecientes)
            ->orderByRaw('FIELD(id, ' . implode(',', $idsRecientes) . ')')
            ->get();
    }

    /** Agrega el cliente de una conversación al grupo seleccionado. */
    public function agregarConversacionAGrupo(int $conversacionId, int $grupoId): void
    {
        $conv = ConversacionWhatsapp::find($conversacionId);
        $grupo = \App\Models\GrupoCliente::find($grupoId);
        if (!$conv || !$conv->cliente_id || !$grupo) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'La conversación no tiene cliente asociado.']);
            return;
        }
        $grupo->clientes()->syncWithoutDetaching([$conv->cliente_id]);
        $this->dispatch('notify', ['type' => 'success', 'message' => "Agregado a '{$grupo->nombre}'."]);
    }

    /** Quita el cliente de la conversación del grupo actualmente filtrado. */
    public function quitarConversacionDeGrupo(int $conversacionId): void
    {
        $conv = ConversacionWhatsapp::find($conversacionId);
        $grupo = $this->grupoFiltroId ? \App\Models\GrupoCliente::find($this->grupoFiltroId) : null;
        if ($conv && $conv->cliente_id && $grupo) {
            $grupo->clientes()->detach($conv->cliente_id);
            $this->dispatch('notify', ['type' => 'info', 'message' => "Quitado de '{$grupo->nombre}'."]);
        }
    }

    public function toggleFijar(int $conversacionId): void
    {
        $conv = ConversacionWhatsapp::find($conversacionId);
        if (!$conv) return;

        if ($conv->fijada_at) {
            $conv->update(['fijada_at' => null]);
            $this->dispatch('notify', ['type' => 'info', 'message' => '📌 Conversación desfijada']);
        } else {
            $conv->update(['fijada_at' => now()]);
            $this->dispatch('notify', ['type' => 'success', 'message' => '📌 Conversación fijada arriba']);
        }
    }

    /**
     * 👁️ Marcar / desmarcar como no leída manualmente.
     * Override sobre no_leidos: aunque la abras, sigue resaltada hasta desmarcarla.
     */
    public function toggleMarcarNoLeida(int $conversacionId): void
    {
        $conv = ConversacionWhatsapp::find($conversacionId);
        if (!$conv) return;

        if ($conv->marcada_no_leida) {
            $conv->update(['marcada_no_leida' => false]);
            $this->dispatch('notify', ['type' => 'info', 'message' => '✓ Marcada como leída']);
        } else {
            $conv->update(['marcada_no_leida' => true]);
            $this->dispatch('notify', ['type' => 'info', 'message' => '🔵 Marcada como no leída']);
        }
    }

    /**
     * 💬 Inicia el modo "responder a un mensaje específico" (estilo WhatsApp).
     * Setea el state para que el composer muestre la cita y el próximo envío
     * incluya context.message_id apuntando a este mensaje.
     */
    public ?bool $respondiendoEsMeta = null; // true=cliente lo verá, false=solo local

    public function iniciarRespuesta(int $mensajeId): void
    {
        $m = MensajeWhatsapp::find($mensajeId);
        if (!$m) return;

        $this->respondiendoAMensajeId = $m->id;
        $this->respondiendoAAutor     = $m->rol === MensajeWhatsapp::ROL_USER ? 'Cliente' : 'Tú';
        $this->respondiendoEsMeta     = $m->mensaje_externo_id && str_starts_with($m->mensaje_externo_id, 'wamid.');

        // Preview corto del contenido
        $preview = trim((string) $m->contenido);
        if ($m->tipo === 'image')    $preview = '🖼️ Imagen';
        elseif ($m->tipo === 'video') $preview = '🎬 Video';
        elseif ($m->tipo === 'audio') $preview = '🎤 Audio';
        elseif ($m->tipo === 'document') {
            $nombreDoc = $m->meta['filename'] ?? 'Documento';
            $preview = "📄 {$nombreDoc}";
        }
        $this->respondiendoAPreview = mb_substr($preview, 0, 120);
    }

    public function cancelarRespuesta(): void
    {
        $this->respondiendoAMensajeId = null;
        $this->respondiendoAPreview   = '';
        $this->respondiendoAAutor     = '';
        $this->respondiendoEsMeta     = null;
    }

    /**
     * ⎋ Cierra la conversación activa (ESC en el chat, estilo WhatsApp Web).
     */
    #[On('cerrar-conversacion-activa')]
    public function cerrarConversacionActiva(): void
    {
        $this->conversacionActivaId = null;
        $this->nuevoMensaje         = '';
        $this->cancelarRespuesta();
        $this->dispatch('conversacion-cerrada');
    }

    /**
     * 💡 Modo shadow: el copiloto pide copiar su sugerencia al composer.
     * SOLO copia el texto — NO envía nada. El operador decide enviarlo.
     */
    #[On('copiloto-usar')]
    public function copilotoUsar(string $texto = ''): void
    {
        if ($texto !== '') {
            $this->nuevoMensaje = $texto;
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Sugerencia copiada. Revísala y envíala tú.']);
        }
    }

    /**
     * 👍 Reacciona a un mensaje del cliente con un emoji.
     * Si ya hay la misma reacción, la quita (toggle). Si hay otra, la cambia.
     */
    public function reaccionarMensaje(int $mensajeId, string $emoji): void
    {
        $emoji = trim($emoji);
        if (mb_strlen($emoji) > 16) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Emoji inválido.']);
            return;
        }

        $mensaje = MensajeWhatsapp::find($mensajeId);
        if (!$mensaje) return;

        // Toggle: si el operador ya puso ESE mismo emoji, lo quita
        $emojiActual = $mensaje->reaccion_operador;
        $nuevoEmoji  = ($emojiActual === $emoji) ? '' : $emoji;

        $conv = ConversacionWhatsapp::find($mensaje->conversacion_id);
        if (!$conv) return;

        // Sólo via Meta — TecnoByteApp no soporta reactions estándar
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            if (!$tenant || $tenant->proveedorWhatsappResuelto() !== \App\Models\Tenant::WA_PROVIDER_META) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'Las reacciones solo funcionan con Meta Cloud API.']);
                return;
            }

            $messageId = $mensaje->mensaje_externo_id;
            $puedeEnviarAMeta = $messageId && str_starts_with($messageId, 'wamid.');

            if ($puedeEnviarAMeta) {
                // Reacción REAL: el cliente la ve en su WhatsApp
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarReaccion($conv->telefono_normalizado, $messageId, $nuevoEmoji, $tenant->id);

                if (!$ok) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => '❌ Meta rechazó la reacción']);
                    return;
                }

                $mensaje->update([
                    'reaccion_operador'    => $nuevoEmoji ?: null,
                    'reaccion_operador_at' => $nuevoEmoji ? now() : null,
                ]);

                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => $nuevoEmoji ? "Reaccionaste {$nuevoEmoji} (cliente lo ve)" : '✓ Reacción quitada',
                ]);
            } else {
                // Reacción LOCAL: marca interna del equipo, el cliente NO la ve
                $mensaje->update([
                    'reaccion_operador'    => $nuevoEmoji ?: null,
                    'reaccion_operador_at' => $nuevoEmoji ? now() : null,
                ]);

                $this->dispatch('notify', [
                    'type'    => 'info',
                    'message' => $nuevoEmoji
                        ? "🔖 Marcada {$nuevoEmoji} (solo visible para el equipo)"
                        : '✓ Marca quitada',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando reacción: ' . $e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function devolverAlBot(): void
    {
        if (!$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) return;

        // 🏢 Al devolver al bot, también limpiamos la derivación al departamento
        // para que la conversación vuelva al pool general (visible para todos
        // los agentes de nuevo, hasta que el bot la derive otra vez si aplica).
        $deptoAnterior = $conv->departamento_id;
        $conv->update([
            'atendida_por_humano' => false,
            'departamento_id'     => null,
            'derivada_at'         => null,
        ]);

        // Registrar en mensajes del sistema para auditoria
        try {
            \App\Models\MensajeWhatsapp::create([
                'conversacion_id'  => $conv->id,
                'rol'              => 'system',
                'tipo'             => 'system_note',
                'contenido'        => $deptoAnterior
                    ? '🔄 Conversación devuelta al bot (departamento anterior liberado).'
                    : '🔄 Conversación devuelta al bot.',
            ]);
        } catch (\Throwable $e) { /* ignore */ }

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => '🤖 El bot retoma la conversación. Volvió al área general.',
        ]);
    }

    /**
     * Recibe audio grabado en el navegador (data URL base64), lo guarda en
     * storage público, y lo envía al cliente por WhatsApp con mediaUrl.
     */
    public function enviarAudio(string $dataUrl): void
    {
        // 👥 Si hay un grupo abierto, enviar el audio a TODOS los miembros.
        if ($this->grupoAbiertoId) {
            $this->broadcastAudioAGrupo($dataUrl);
            return;
        }

        if (!$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // Decodificar data URL — aceptamos parámetros del mime (ej. "audio/webm;codecs=opus")
        if (!preg_match('/^data:(audio\/[^;,]+(?:;[^,]*)?);base64,(.+)$/i', $dataUrl, $m)) {
            Log::warning('Audio data URL no reconocida', ['preview' => substr($dataUrl, 0, 80)]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de audio no reconocido.']);
            return;
        }

        $mime  = strtolower(explode(';', $m[1])[0]);   // solo el tipo base, sin codecs
        $bytes = base64_decode($m[2], true);

        if ($bytes === false || strlen($bytes) < 100) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Audio inválido o vacío.']);
            return;
        }

        if (strlen($bytes) > 16 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Audio demasiado grande (máx 16 MB).']);
            return;
        }

        $ext = match (true) {
            str_contains($mime, 'ogg')  => 'ogg',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'mpeg') => 'mp3',
            str_contains($mime, 'mp4')  => 'm4a',
            str_contains($mime, 'wav')  => 'wav',
            default                     => 'webm',
        };

        $filename = 'audios-out/audio_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($filename, $bytes);

        // URL pública para nuestro propio chat web (para reproducir en el Chat en vivo).
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        // Convertir a .ogg/opus — WhatsApp solo reproduce nota de voz en ese formato.
        // Si no está ffmpeg o la conversión falla, enviamos el archivo tal cual.
        [$bytesEnvio, $extEnvio] = $this->convertirAOggOpus($bytes, $ext);

        $nombreEnvio = 'voice_' . uniqid() . '.' . $extEnvio;

        // 🟢 RUTA META: si el tenant usa Meta, mandamos el audio por URL pública
        // a la Cloud API. Meta NO acepta video/webm — debe ser audio/mpeg, ogg,
        // amr, mp4 o aac. Persistimos el archivo YA CONVERTIDO y usamos esa URL.
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            if ($tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META) {
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                if (!$checker->abierta($conv)) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '🔒 Ventana 24h cerrada. Meta no permite enviar audio fuera de la ventana — pide al cliente que escriba primero o envía una plantilla.',
                    ]);
                    return;
                }

                // Guardar el archivo CONVERTIDO (mp3) en storage público y
                // generar URL pública para que Meta pueda descargarlo.
                $filenameConv = 'audios-out/audio_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extEnvio;
                Storage::disk('public')->put($filenameConv, $bytesEnvio);
                $mediaUrlMeta = rtrim(config('app.url'), '/') . Storage::url($filenameConv);

                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarAudio($conv->telefono_normalizado, $mediaUrlMeta, $conv->tenant_id);

                if (!$ok) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '⚠️ Meta rechazó el audio. Revisa logs.',
                    ]);
                    return;
                }

                try {
                    $mensaje = app(ConversacionService::class)->agregarMensaje(
                        $conv,
                        MensajeWhatsapp::ROL_ASSISTANT,
                        '🎤 Nota de voz',
                        [
                            'tipo' => 'audio',
                            'meta' => [
                                'enviado_por_humano' => true,
                                'usuario_id'         => auth()->id(),
                                'media_url'          => $mediaUrlMeta,
                                'mime'               => 'audio/mpeg',
                                'bytes'              => strlen($bytesEnvio),
                                'provider'           => 'meta',
                            ],
                        ]
                    );
                    $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
                } catch (\Throwable $e) {
                    Log::warning('No persistió audio Meta: ' . $e->getMessage());
                }

                $this->dispatch('mensaje-enviado');
                $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Audio enviado por Meta']);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Ruta Meta audio falló (cae a legacy): ' . $e->getMessage());
        }

        // ─── RUTA LEGACY (TecnoByteApp) ─────────────────────────────
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);
        $messageId = $this->enviarAudioAWhatsapp($conv->telefono_normalizado, $bytesEnvio, $nombreEnvio, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar el audio a WhatsApp.']);
            return;
        }

        // Persistir como mensaje del operador con tipo audio y URL
        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                '🎤 Nota de voz',
                [
                    'tipo' => 'audio',
                    'meta' => [
                        'enviado_por_humano' => true,
                        'usuario_id'         => auth()->id(),
                        'media_url'          => $mediaUrl,
                        'mime'               => $mime,
                        'bytes'              => strlen($bytes),
                    ],
                ]
            );
            // Marcar como enviado + guardar ID externo para que los ticks actualicen
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió audio manual: ' . $e->getMessage());
        }

        $this->dispatch('mensaje-enviado');
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Audio enviado']);
    }

    /**
     * Convierte el audio grabado en el navegador a MP3 mono 44.1 kHz 64kbps.
     * MP3 se reproduce correctamente en WhatsApp móvil y desktop (como audio
     * adjunto, no voice note, pero reproducible 100% del tiempo).
     * El formato OGG/Opus como voice note causa "audio no disponible" en
     * WhatsApp móvil cuando no está generado con parámetros exactos.
     *
     * @return array{0: string, 1: string} [bytesConvertidos, extension]
     */
    private function convertirAOggOpus(string $bytes, string $extOriginal): array
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'wa_in_') . '.' . $extOriginal;
        $tmpOut = tempnam(sys_get_temp_dir(), 'wa_out_') . '.mp3';

        try {
            file_put_contents($tmpIn, $bytes);

            $process = new Process([
                'ffmpeg', '-y',
                '-i', $tmpIn,
                '-vn',
                '-c:a', 'libmp3lame',
                '-b:a', '64k',
                '-ar', '44100',
                '-ac', '1',
                $tmpOut,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful() || !file_exists($tmpOut) || filesize($tmpOut) < 100) {
                Log::warning('🎤 ffmpeg falló al convertir a mp3, enviando original', [
                    'stderr' => substr($process->getErrorOutput(), 0, 500),
                ]);
                return [$bytes, $extOriginal];
            }

            $converted = file_get_contents($tmpOut);
            return [$converted, 'mp3'];
        } catch (\Throwable $e) {
            Log::warning('🎤 Excepción al convertir audio: ' . $e->getMessage());
            return [$bytes, $extOriginal];
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    /**
     * Envía un audio a WhatsApp vía TecnoByteApp.
     * TecnoByteApp espera el archivo como multipart/form-data en el campo `medias`
     * (no acepta mediaUrl). El mimetype del archivo hace que se envíe como voice note.
     */
    private function enviarAudioAWhatsapp(string $telefono, string $bytes, string $filename, $connectionId = null): ?string
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para audio manual');
                return null;
            }

            Log::info('🎤 ENVIANDO AUDIO WHATSAPP', [
                'phone'    => $telefono,
                'filename' => $filename,
                'bytes'    => strlen($bytes),
            ]);

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            $makeRequest = function (string $useToken) use ($endpointSend, $telefono, $bytes, $filename, $connectionId) {
                $req = Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->asMultipart()
                    ->attach('medias', $bytes, $filename);

                $form = [
                    ['name' => 'number', 'contents' => $telefono],
                ];
                if ($connectionId) {
                    $form[] = ['name' => 'whatsappId', 'contents' => (string) $connectionId];
                }

                // Laravel's Http::attach+post con array: usa la API más simple:
                return Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->attach('medias', $bytes, $filename)
                    ->post($endpointSend, $connectionId ? [
                        'number'     => $telefono,
                        'whatsappId' => (int) $connectionId,
                    ] : [
                        'number' => $telefono,
                    ]);
            };

            $response = $makeRequest($token);

            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = $makeRequest($newToken);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío audio WhatsApp falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando audio WhatsApp: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Envía una imagen desde el chat al cliente por WhatsApp.
     * Recibe la imagen como data URL base64 (enviada desde JS) para
     * evitar el endpoint /livewire/upload-file (que tira 401 en producción).
     */
    /**
     * 📄 Envía un documento (PDF, Word, Excel...) al cliente.
     * Recibe un data URL base64 desde el composer + nombre original.
     */
    public function enviarDocumento(string $dataUrl = '', string $filename = '', string $caption = ''): void
    {
        if (!$this->conversacionActivaId) return;
        if ($dataUrl === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona un documento primero.']);
            return;
        }

        if (!preg_match('/^data:([^;]+);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de documento no reconocido.']);
            return;
        }

        $mime  = strtolower(trim($m[1]));
        $bytes = base64_decode($m[2], true);

        if ($bytes === false || strlen($bytes) < 50) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Documento inválido o vacío.']);
            return;
        }
        if (strlen($bytes) > 90 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Documento demasiado grande (máx 90 MB).']);
            return;
        }

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // Nombre y extensión seguros
        $nombre = trim($filename) !== '' ? trim($filename) : 'documento';
        $nombre = preg_replace('/[\\/\\\\:*?"<>|]+/', '_', $nombre);
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION) ?: match (true) {
            str_contains($mime, 'pdf')        => 'pdf',
            str_contains($mime, 'word')       => 'docx',
            str_contains($mime, 'sheet')      => 'xlsx',
            str_contains($mime, 'excel')      => 'xls',
            str_contains($mime, 'presentation') => 'pptx',
            default                            => 'bin',
        });
        if (pathinfo($nombre, PATHINFO_EXTENSION) === '') $nombre .= '.' . $ext;

        $caption = trim($caption);

        // Guardar copia pública para enviar por URL y mostrar en el chat
        $stored = 'documentos-out/doc_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($stored, $bytes);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($stored);

        // 🟢 RUTA META
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            if ($tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META) {
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                if (!$checker->abierta($conv)) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => '🔒 Ventana 24h cerrada. Usa el panel ámbar de plantilla para reabrir.']);
                    return;
                }

                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarDocumento($conv->telefono_normalizado, $mediaUrl, $nombre, $caption ?: null, $conv->tenant_id);

                if (!$ok) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Meta rechazó el documento.']);
                    return;
                }

                try {
                    $mensaje = app(ConversacionService::class)->agregarMensaje(
                        $conv,
                        MensajeWhatsapp::ROL_ASSISTANT,
                        $caption !== '' ? $caption : ('📄 ' . $nombre),
                        [
                            'tipo' => 'document',
                            'meta' => [
                                'enviado_por_humano' => true,
                                'usuario_id'         => auth()->id(),
                                'media_url'          => $mediaUrl,
                                'filename'           => $nombre,
                                'caption'            => $caption,
                                'provider'           => 'meta',
                            ],
                        ]
                    );
                    $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
                } catch (\Throwable $e) {
                    Log::warning('No persistió documento Meta: ' . $e->getMessage());
                }

                $this->dispatch('mensaje-enviado');
                $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Documento enviado']);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Ruta Meta documento falló: ' . $e->getMessage());
        }

        // Si el tenant NO usa Meta, por ahora no soportamos envío de documentos.
        $this->dispatch('notify', ['type' => 'error', 'message' => 'El envío de documentos solo está disponible en conexiones Meta por ahora.']);
    }

    public function enviarImagen(string $dataUrl = '', string $caption = ''): void
    {
        // 👥 Si hay un grupo abierto, enviar la imagen a TODOS los miembros.
        if ($this->grupoAbiertoId) {
            $this->broadcastImagenAGrupo($dataUrl, $caption);
            return;
        }

        if (!$this->conversacionActivaId) return;
        if ($dataUrl === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona una imagen primero.']);
            return;
        }

        if (!preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de imagen no reconocido.']);
            return;
        }

        $mime  = strtolower($m[1]);
        $bytes = base64_decode($m[2], true);

        if ($bytes === false || strlen($bytes) < 100) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Imagen inválida o vacía.']);
            return;
        }

        if (strlen($bytes) > 15 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Imagen demasiado grande (máx 15 MB).']);
            return;
        }

        $extOrig = match (true) {
            str_contains($mime, 'png')  => 'png',
            str_contains($mime, 'gif')  => 'gif',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'bmp')  => 'bmp',
            default                     => 'jpg',
        };

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        $caption = trim($caption);

        // Guardar copia pública para mostrar en el chat web
        $filename = 'imagenes-out/img_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extOrig;
        Storage::disk('public')->put($filename, $bytes);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        $nombreEnvio = 'image_' . uniqid() . '.' . $extOrig;

        // 🟢 RUTA META: tenant con Meta envía imagen vía URL pública
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            if ($tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META) {
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                if (!$checker->abierta($conv)) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '🔒 Ventana 24h cerrada. Para enviar imagen, usa el panel ámbar de plantilla para reabrir.',
                    ]);
                    return;
                }

                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarImagen($conv->telefono_normalizado, $mediaUrl, $caption ?: null, $conv->tenant_id);

                if (!$ok) {
                    $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Meta rechazó la imagen.']);
                    return;
                }

                try {
                    $mensaje = app(ConversacionService::class)->agregarMensaje(
                        $conv,
                        MensajeWhatsapp::ROL_ASSISTANT,
                        $caption !== '' ? $caption : '🖼️ Imagen',
                        [
                            'tipo' => 'image',
                            'meta' => [
                                'enviado_por_humano' => true,
                                'usuario_id'         => auth()->id(),
                                'media_url'          => $mediaUrl,
                                'caption'            => $caption,
                                'provider'           => 'meta',
                            ],
                        ]
                    );
                    $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
                } catch (\Throwable $e) {
                    Log::warning('No persistió imagen Meta: ' . $e->getMessage());
                }

                $this->dispatch('mensaje-enviado');
                $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Imagen enviada por Meta']);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Ruta Meta imagen falló (cae a legacy): ' . $e->getMessage());
        }

        // ─── RUTA LEGACY (TecnoByteApp) ─────────────────────────────
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);
        $messageId = $this->enviarImagenAWhatsapp($conv->telefono_normalizado, $bytes, $nombreEnvio, $caption, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar la imagen a WhatsApp.']);
            return;
        }

        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $caption !== '' ? $caption : '🖼️ Imagen',
                [
                    'tipo' => 'image',
                    'meta' => [
                        'enviado_por_humano' => true,
                        'usuario_id'         => auth()->id(),
                        'media_url'          => $mediaUrl,
                        'caption'            => $caption,
                    ],
                ]
            );
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió imagen manual: ' . $e->getMessage());
        }

        $this->dispatch('mensaje-enviado');
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Imagen enviada']);
    }

    /**
     * Envía una imagen a WhatsApp vía TecnoByteApp (multipart 'medias').
     */
    private function enviarImagenAWhatsapp(string $telefono, string $bytes, string $filename, string $caption, $connectionId = null): ?string
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para imagen manual');
                return null;
            }

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            Log::info('🖼️ ENVIANDO IMAGEN WHATSAPP', [
                'phone'    => $telefono,
                'filename' => $filename,
                'bytes'    => strlen($bytes),
                'caption'  => $caption,
            ]);

            $payload = ['number' => $telefono];
            if ($caption !== '')       $payload['body']       = $caption;
            if ($connectionId)         $payload['whatsappId'] = (int) $connectionId;

            $makeRequest = function (string $useToken) use ($endpointSend, $bytes, $filename, $payload) {
                return Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->attach('medias', $bytes, $filename)
                    ->post($endpointSend, $payload);
            };

            $response = $makeRequest($token);
            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = $makeRequest($newToken);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío imagen WhatsApp falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando imagen WhatsApp: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resuelve la connection_id a usar para enviar un mensaje.
     * Prioridad:
     *   1. La que ya tiene la conversación.
     *   2. La primera connection_id registrada para el tenant actual.
     * Si no hay ninguna, devuelve null y TecnoByteApp fallará con mensaje claro
     * (mejor que usar la default de OTRO tenant).
     */
    private function resolverConnectionId($connectionIdActual = null): ?int
    {
        // connectionIdsValidos() consulta TecnoByteApp y filtra solo los IDs
        // que EXISTEN y están CONNECTED. Auto-descarta huérfanos (ej. id viejo
        // tras recrear QR) y actualiza la BD del tenant + migra conversaciones
        // sin intervención manual.
        $ids = app(\App\Services\WhatsappResolverService::class)->connectionIdsValidos();

        if ($connectionIdActual) {
            $cid = (int) $connectionIdActual;
            if (in_array($cid, $ids, true)) {
                return $cid;
            }
            \Log::warning('connection_id de la conversación huérfano — auto-corrigiendo', [
                'cid_invalido' => $cid,
                'ids_validos'  => $ids,
            ]);
        }

        return !empty($ids) ? (int) $ids[0] : null;
    }

    public function abrirNuevoChat(): void
    {
        $this->nuevoChatModal   = true;
        $this->nuevoChatTel     = '';
        $this->nuevoChatNombre  = '';
        $this->nuevoChatMensaje = '';
    }

    public function cerrarNuevoChat(): void
    {
        $this->nuevoChatModal = false;
    }

    /**
     * Crea (o abre si ya existe) una conversación para un número nuevo y envía
     * el primer mensaje. Si el número nunca ha escrito, lo creamos desde cero.
     */
    public function crearNuevoChat(): void
    {
        $telefono = preg_replace('/\D+/', '', (string) $this->nuevoChatTel);
        $texto    = trim($this->nuevoChatMensaje);

        if ($telefono === '' || strlen($telefono) < 8) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ingresa un teléfono válido (ej. 573001234567).']);
            return;
        }
        if ($texto === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Escribe el primer mensaje.']);
            return;
        }

        // 🟢 RUTA META: si el tenant usa Meta, no necesitamos connection_id de TecnoByteApp.
        // Iniciamos directo via Cloud API. Si el cliente nunca te ha escrito y la ventana 24h
        // está cerrada, Meta exigirá plantilla y avisamos al usuario.
        $tenant      = app(\App\Services\TenantManager::class)->current();
        $tenantUsaMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;

        if ($tenantUsaMeta) {
            $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telefono, trim($this->nuevoChatNombre) ?: 'Cliente');
            $conv = app(ConversacionService::class)->obtenerOCrearActiva($telefono, $cliente->id, null, null);

            try {
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarTexto($telefono, $texto, $tenant->id);

                if (!$ok) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '🔒 Cliente nuevo o ventana 24h cerrada. Por Meta debes enviar una PLANTILLA aprobada para iniciar la conversación. Cierra este modal y usa el panel ámbar "Enviar plantilla" del chat.',
                    ]);
                    return;
                }

                try {
                    $mensaje = app(ConversacionService::class)->agregarMensaje(
                        $conv,
                        MensajeWhatsapp::ROL_ASSISTANT,
                        $texto,
                        ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id(), 'provider' => 'meta']]
                    );
                    $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
                } catch (\Throwable $e) {
                    Log::warning('No se persistió mensaje manual Meta (nuevo chat): ' . $e->getMessage());
                }

                $this->nuevoChatModal       = false;
                $this->conversacionActivaId = $conv->id;
                $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Chat iniciado por Meta']);
                $this->dispatch('chat-cambiado', conversacionId: $conv->id);
                return;
            } catch (\Throwable $e) {
                Log::error('Error iniciando chat Meta: ' . $e->getMessage());
                $this->dispatch('notify', ['type' => 'error', 'message' => '❌ Error Meta: ' . $e->getMessage()]);
                return;
            }
        }

        // ─── RUTA LEGACY (TecnoByteApp) ─────────────────────────────
        // Resolver connection_id del tenant actual ANTES de crear la conversación
        $connectionId = $this->resolverConnectionId();
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }

        // Resolver/crear cliente + conversación (con connection_id del tenant)
        $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telefono, trim($this->nuevoChatNombre) ?: 'Cliente');
        $conv = app(ConversacionService::class)->obtenerOCrearActiva($telefono, $cliente->id, null, $connectionId);

        // Si la conversación ya existía pero sin connection_id, la actualizamos
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);

        $messageId = $this->enviarAWhatsapp($telefono, $texto, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar el mensaje. Verifica que el número sí use WhatsApp.']);
            return;
        }

        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $texto,
                ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
            );
            $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT, 'mensaje_externo_id' => $messageId]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió mensaje manual (nuevo chat): ' . $e->getMessage());
        }

        $this->nuevoChatModal        = false;
        $this->conversacionActivaId  = $conv->id;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Chat iniciado']);
        $this->dispatch('chat-cambiado', conversacionId: $conv->id);
    }

    public function enviar(): void
    {
        $texto = trim($this->nuevoMensaje);
        if ($texto === '' || !$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // ⚠️ NO se auto-activa modo humano. El bot SIGUE respondiendo al cliente.
        // Si quieres silenciar al bot, usa el botón "Tomar control" explícitamente.

        // Si la conversación es de un WIDGET WEB (no WhatsApp), no usamos TecnoByteApp.
        // El widget pollea los mensajes del operador y los muestra.
        if ($conv->canal === 'widget') {
            try {
                $mensaje = app(ConversacionService::class)->agregarMensaje(
                    $conv,
                    MensajeWhatsapp::ROL_ASSISTANT,
                    $texto,
                    ['meta' => [
                        'enviado_por_humano'  => true,
                        'usuario_id'          => auth()->id(),
                        'canal_widget'        => true,
                    ]]
                );
                $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
            } catch (\Throwable $e) {
                Log::warning('No se persistió mensaje widget: ' . $e->getMessage());
            }

            $this->nuevoMensaje = '';
            $this->dispatch('mensaje-enviado');
            $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Enviado al widget web']);
            return;
        }

        // 🟢 RUTA META: si el tenant usa Meta, mandamos directo por la Cloud API
        // (sin connection_id de TecnoByteApp). Si la ventana 24h está cerrada,
        // bloqueamos y sugerimos enviar plantilla con el panel de arriba.
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            if ($tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META) {
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                if (!$checker->abierta($conv)) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '🔒 Ventana 24h cerrada. Usa el panel ámbar de arriba para enviar una plantilla aprobada.',
                    ]);
                    return;
                }

                // 💬 ¿Es respuesta a un mensaje específico? Usar context.message_id
                $mensajeRespondido = null;
                $wamidReferencia   = null;
                if ($this->respondiendoAMensajeId) {
                    $mensajeRespondido = MensajeWhatsapp::find($this->respondiendoAMensajeId);
                    if ($mensajeRespondido && $mensajeRespondido->mensaje_externo_id
                        && str_starts_with($mensajeRespondido->mensaje_externo_id, 'wamid.')) {
                        $wamidReferencia = $mensajeRespondido->mensaje_externo_id;
                    }
                }

                $svc = app(\App\Services\Meta\MetaWhatsappCloudService::class);
                $ok = $wamidReferencia
                    ? $svc->enviarTextoRespuesta($conv->telefono_normalizado, $texto, $wamidReferencia, $conv->tenant_id)
                    : $svc->enviarTexto($conv->telefono_normalizado, $texto, $conv->tenant_id);

                // 📌 Capturar wamid del mensaje que acabamos de enviar para futuras citas/reacciones del cliente
                $wamidNuevo = $svc->ultimoWamid;

                if (!$ok) {
                    $this->dispatch('notify', [
                        'type'    => 'error',
                        'message' => '⚠️ Meta rechazó el envío. Revisa logs o credenciales en /meta-whatsapp.',
                    ]);
                    return;
                }

                try {
                    $msg = app(ConversacionService::class)->agregarMensaje(
                        $conv,
                        MensajeWhatsapp::ROL_ASSISTANT,
                        $texto,
                        ['meta' => [
                            'enviado_por_humano' => true,
                            'usuario_id'         => auth()->id(),
                            'provider'           => 'meta',
                        ]]
                    );
                    // Guardar referencia al mensaje respondido para renderizar la cita en UI
                    $updates = [];
                    if ($mensajeRespondido) {
                        $updates['respondiendo_a_mensaje_id'] = $mensajeRespondido->id;
                    }
                    // 📌 Persistir wamid del mensaje recién enviado — permite que cuando el
                    // cliente lo cite/reaccione, podamos vincularlo correctamente.
                    if ($wamidNuevo) {
                        $updates['mensaje_externo_id'] = $wamidNuevo;
                    }
                    if (!empty($updates)) {
                        $msg->update($updates);
                    }
                } catch (\Throwable $e) {
                    Log::warning('No persistió mensaje manual Meta: ' . $e->getMessage());
                }

                $this->nuevoMensaje = '';
                $this->cancelarRespuesta();
                $this->dispatch('mensaje-enviado');
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => $wamidReferencia ? '✓ Respuesta enviada por Meta' : '✓ Mensaje enviado por Meta',
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Ruta Meta chat falló (cae a legacy): ' . $e->getMessage());
        }

        // ─── RUTA LEGACY (TecnoByteApp) ─────────────────────────────
        // Resolver connection_id del tenant actual (evita enviar desde el número de otro tenant)
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada. Configúrala primero.',
            ]);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);

        // Enviar a WhatsApp DIRECTO (sin pasar por controller)
        $messageId = $this->enviarAWhatsapp($conv->telefono_normalizado, $texto, $connectionId);

        if (!$messageId) {
            $causa = $this->diagnosticarFalloEnvio() ?? 'Revisa el token WhatsApp del tenant.';
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ No se pudo enviar: ' . $causa,
            ]);
            return;
        }

        // Persistir como mensaje del operador con ack=sent y ID externo
        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $texto,
                ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
            );
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió mensaje manual: ' . $e->getMessage());
        }

        $this->nuevoMensaje = '';
        $this->dispatch('mensaje-enviado');

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Mensaje enviado',
        ]);
    }

    /**
     * Envía un mensaje a WhatsApp directamente vía TecnoByteApp.
     */
    /**
     * Verifica que el connection_id que se va a usar pertenece al usuario
     * autenticado en TecnoByteApp. Si no, devuelve un mensaje específico.
     */
    private function debugEnvio(string $telefono, $connectionId): void
    {
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'null';
        $ids = $resolver->connectionIdsDelTenant();

        Log::info('🔎 DEBUG envio chat', [
            'tenant_actual'       => $tenantId,
            'email_usado'         => $cred['email'] ?? '???',
            'api_base_url'        => $cred['api_base_url'] ?? '???',
            'connectionIdsTenant' => $ids,
            'connection_id_usado' => $connectionId,
            'telefono'            => $telefono,
            'cache_key'           => $resolver->tokenCacheKey(),
        ]);
    }

    private function enviarAWhatsapp(string $telefono, string $mensaje, $connectionId = null): ?string
    {
        $this->debugEnvio($telefono, $connectionId);
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para chat manual');
                try {
                    app(\App\Services\BotAlertaService::class)->registrar(
                        \App\Models\BotAlerta::TIPO_WHATSAPP_TOKEN,
                        '📱 Token de WhatsApp no disponible (chat manual)',
                        'No se pudo obtener el token de WhatsApp al intentar enviar un mensaje manual desde el chat interno. Verifica WHATSAPP_API_EMAIL / WHATSAPP_API_PASSWORD en .env.',
                        \App\Models\BotAlerta::SEV_CRITICA,
                        null,
                        ['telefono' => $telefono]
                    );
                } catch (\Throwable $e) { /* no bloquear */ }
                return null;
            }

            // TecnoByteApp solo usa 'whatsappId'. NO mandar 'connectionId',
            // confunde al wrapper y devuelve ERR_SENDING_WAPP_MSG.
            $payload = [
                'number' => $telefono,
                'body'   => $mensaje,
            ];
            if ($connectionId) {
                $payload['whatsappId'] = (int) $connectionId;
            }

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post($endpointSend, $payload);

            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            // Reintento con refresh de token (401 = JWT expirado)
            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = Http::withoutVerifying()
                        ->withToken($newToken)
                        ->timeout(20)
                        ->post($endpointSend, $payload);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            // ── AUTO-RECOVERY: si la API responde ERR_NO_WAPP_FOUND, el id
            // está huérfano. Refrescamos validados (que actualiza la BD) y
            // reintentamos con el id correcto. Cero intervención manual.
            $bodyTxt = (string) $response->body();
            if (str_contains($bodyTxt, 'ERR_NO_WAPP_FOUND') || $response->status() === 404) {
                Cache::forget("wa_valid_conn_ids_t" . (app(\App\Services\TenantManager::class)->id() ?? 'g'));
                $idsValidos = $resolver->connectionIdsValidos();
                $nuevoId = $idsValidos[0] ?? null;

                if ($nuevoId && $nuevoId !== (int) ($payload['whatsappId'] ?? 0)) {
                    Log::info('🔄 Reintentando con connection_id auto-corregido', [
                        'antes'   => $payload['whatsappId'] ?? null,
                        'despues' => $nuevoId,
                    ]);
                    $payload['whatsappId'] = $nuevoId;
                    $retry = Http::withoutVerifying()->withToken($token)->timeout(20)->post($endpointSend, $payload);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío WhatsApp manual falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            try {
                app(\App\Services\BotAlertaService::class)->registrar(
                    \App\Models\BotAlerta::TIPO_WHATSAPP_ENVIO,
                    '📤 Falló envío manual de WhatsApp',
                    'Un mensaje enviado desde el chat interno no pudo entregarse. Status ' . $response->status() . '.',
                    \App\Models\BotAlerta::SEV_WARNING,
                    $response->status(),
                    [
                        'telefono' => $telefono,
                        'body'     => mb_substr((string) $response->body(), 0, 500),
                    ]
                );
            } catch (\Throwable $e) { /* no bloquear */ }

            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando WA manual: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el token cacheado o hace login a TecnoByteApp.
     */
    /**
     * Obtiene el token JWT del API. Delega al WhatsappResolverService que
     * centraliza login/refresh con mutex y caché compartida.
     */
    private function obtenerTokenWhatsapp(bool $forzarFresh = false): ?string
    {
        return app(\App\Services\WhatsappResolverService::class)->token(null, $forzarFresh);
    }

    /**
     * Diagnóstico para mensajes de error claros al operador.
     * Devuelve null si todo OK, o un string con la causa exacta.
     */
    private function diagnosticarFalloEnvio(): ?string
    {
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $ids  = $resolver->connectionIdsDelTenant();

        if (empty($cred['email']) || empty($cred['password'])) {
            return 'Este tenant no tiene credenciales TecnoByteApp configuradas. '
                 . 'Ve a /admin/tenants → Editar → "WhatsApp (TecnoByteApp)" y agrega email + password.';
        }
        if (empty($ids)) {
            return 'Este tenant no tiene connection_ids asignados. '
                 . 'Ve a /admin/tenants → Editar → "Connection IDs de TecnoByteApp".';
        }

        // Las credenciales existen e ids también — probamos el login en VIVO
        // para distinguir si falla el login o falla el send.
        try {
            $url = rtrim($cred['api_base_url'], '/') . '/auth/login';
            $resp = Http::withoutVerifying()->timeout(10)->post($url, [
                'email'    => $cred['email'],
                'password' => $cred['password'],
            ]);

            if (!$resp->successful() || !$resp->json('token')) {
                return 'Las credenciales WhatsApp del tenant fueron rechazadas por TecnoByteApp '
                     . '(status ' . $resp->status() . '). Verifica email/password en /admin/tenants → Editar.';
            }
        } catch (\Throwable $e) {
            return 'No se pudo contactar TecnoByteApp (' . $cred['api_base_url'] . '). '
                 . 'Verifica conectividad / cert SSL: ' . $e->getMessage();
        }

        // Login OK → entonces el envío falla por otra razón. Probamos el listado
        // de WhatsApps para detectar específicamente ERR_SESSION_EXPIRED (QR
        // desvinculado), QRCODE pendiente, etc.
        try {
            $token = $resp->json('token');
            $listado = Http::withoutVerifying()->withToken($token)->timeout(10)
                ->get(rtrim($cred['api_base_url'], '/') . '/whatsapp/');

            if ($listado->successful()) {
                $whatsapps = collect($listado->json('whatsapps', []));
                $miConexion = $whatsapps->firstWhere('id', (int) $ids[0]);

                if ($miConexion) {
                    $estado = strtoupper($miConexion['status'] ?? '');
                    $phone  = $miConexion['phoneNumber'] ?? '(sin número)';

                    if ($estado !== 'CONNECTED') {
                        return "📱 El WhatsApp del tenant está '{$estado}' (número {$phone}). "
                             . 'Hay que escanear el QR de nuevo. Ve a /pedidos → arriba a la derecha → "Forzar reconexión" o "Nuevo QR".';
                    }
                }
            }
        } catch (\Throwable $e) { /* ignorar y caer al genérico */ }

        return '⚠️ El WhatsApp del tenant está conectado en login pero TecnoByteApp rechazó el envío. '
             . 'Si te aparece ERR_SESSION_EXPIRED en logs, escanea el QR de nuevo en /pedidos. '
             . 'Otras causas: connection_id ' . implode(',', $ids) . ' no pertenece a este usuario, '
             . 'o número del cliente bloqueado.';
    }

    public function render()
    {
        // 🛡️ Filtro por departamento del usuario actual.
        // - Si el usuario puede ver todas (super-admin / chat.ver-todos / sin dept) → no filtra.
        // - Si tiene departamentos asignados → ve SOLO conversaciones derivadas a SUS departamentos.
        //   (Las conversaciones sin derivar las atiende el bot; los admins/supervisores las ven todas).
        $user = auth()->user();
        $deptoIds = $user?->departamentos()->pluck('departamentos.id')->all() ?? [];
        $verTodas = $user?->puedeVerTodasLasConversaciones() ?? true;

        $convQuery = ConversacionWhatsapp::query()
            ->with(['cliente', 'departamento'])
            ->where('estado', '!=', 'archivada')
            ->when(!$verTodas && !empty($deptoIds), function ($q) use ($deptoIds) {
                // Estricto: solo conversaciones derivadas a algún departamento del agente.
                $q->whereIn('departamento_id', $deptoIds);
            });

        // 🏢 Filtro por sede: si el user tiene sede_id y no es admin/gerente,
        //    solo ve conversaciones de su sede. Admins ven todo.
        $convQuery = \App\Support\SedeScopeFilter::aplicar($convQuery);

        $conversaciones = $convQuery
            ->when($this->busqueda, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('telefono_normalizado', 'like', "%{$this->busqueda}%")
                       ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->busqueda}%"));
                });
            })
            ->when($this->filtroEstado === 'activa',   fn ($q) => $q->where('estado', 'activa'))
            ->when($this->filtroEstado === 'humano',   fn ($q) => $q->where('atendida_por_humano', true))
            ->when($this->filtroEstado === 'bot',      fn ($q) => $q->where('atendida_por_humano', false))
            ->when($this->filtroEstado === 'internos', fn ($q) => $q->where('es_interna', true))
            ->when($this->filtroEstado === 'no_leidos', fn ($q) => $q->where(fn ($qq) =>
                $qq->where('no_leidos', '>', 0)->orWhere('marcada_no_leida', true)
            ))
            ->when($this->filtroEstado === 'favoritos', fn ($q) => $q->whereNotNull('fijada_at'))
            // 👥 Filtro por grupo de clientes: solo conversaciones de clientes en el grupo
            ->when($this->grupoFiltroId, fn ($q) => $q->whereIn('cliente_id',
                \Illuminate\Support\Facades\DB::table('cliente_grupo')
                    ->where('grupo_id', $this->grupoFiltroId)->pluck('cliente_id')
            ))
            ->when($this->filtroEstado !== 'internos' && !$this->mostrarInternas,
                   fn ($q) => $q->where(fn ($qq) => $qq->where('es_interna', false)->orWhereNull('es_interna')))
            // 📡 Filtro por canal (WhatsApp / Instagram / Widget web)
            ->when($this->filtroCanal === 'whatsapp', fn ($q) => $q->where(fn ($qq) => $qq->where('canal', 'whatsapp')->orWhereNull('canal')))
            ->when($this->filtroCanal === 'instagram', fn ($q) => $q->where('canal', 'instagram'))
            ->when($this->filtroCanal === 'messenger', fn ($q) => $q->where('canal', 'messenger'))
            ->when($this->filtroCanal === 'widget',    fn ($q) => $q->where('canal', 'widget'))
            // 📌 Fijadas siempre arriba (ordenadas por cuándo se fijaron, más recientes primero)
            ->orderByRaw('CASE WHEN fijada_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('fijada_at')
            ->orderByDesc('ultimo_mensaje_at')
            // 📜 Scroll infinito: pedimos 1 de más para saber si quedan más por cargar.
            ->limit($this->limiteConversaciones + 1)
            ->get();

        // ¿Hay más allá del límite actual? (trajimos 1 extra a propósito)
        $this->hayMasConversaciones = $conversaciones->count() > $this->limiteConversaciones;
        if ($this->hayMasConversaciones) {
            $conversaciones = $conversaciones->take($this->limiteConversaciones);
        }

        $conversacionActiva = $this->conversacionActivaId
            ? ConversacionWhatsapp::with(['cliente', 'mensajes', 'pedido'])->find($this->conversacionActivaId)
            : null;

        // 📋 Estado estructurado del pedido (para el modal). Solo se carga si
        // el modal está abierto, para no consumir BD innecesariamente.
        $pedidoEstado = null;
        $promptInspeccion = null;
        if ($this->pedidoEstadoModal && $conversacionActiva) {
            try {
                $pedidoEstado = app(\App\Services\EstadoPedidoService::class)
                    ->obtener($conversacionActiva);
            } catch (\Throwable $e) {
                Log::warning('No se pudo cargar estado pedido para modal: ' . $e->getMessage());
            }

            // 🔍 Si la pestaña activa es "prompt", reconstruir el prompt completo.
            if ($this->pedidoEstadoTab === 'prompt') {
                try {
                    $promptInspeccion = app(\App\Services\BotPromptInspectorService::class)
                        ->inspeccionar($conversacionActiva, 10);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo inspeccionar prompt: ' . $e->getMessage());
                    $promptInspeccion = ['error' => $e->getMessage()];
                }
            }
        }

        // 🟢 Meta: plantillas aprobadas disponibles para envío manual
        $plantillasMetaAprobadas = collect();
        $plantillaChatSeleccionada = null;
        if ($this->tenantUsaMeta) {
            // ⚠️ El campo estado puede venir en español ('aprobada') o en formato
            // Meta API ('APPROVED'), dependiendo de cómo se sincronizaron.
            // Aceptamos ambos para máxima compatibilidad.
            $plantillasMetaAprobadas = \App\Models\MetaWhatsappPlantilla::where('activa', true)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(estado)'), ['approved', 'aprobada'])
                ->orderBy('nombre')
                ->get();
            if ($this->plantillaChatId) {
                $plantillaChatSeleccionada = $plantillasMetaAprobadas->firstWhere('id', $this->plantillaChatId);
            }
        }

        // 🟢 Recalcular ventana 24h en cada render (no solo al seleccionar)
        // para que el contador se actualice con el wire:poll.
        if ($this->tenantUsaMeta && $conversacionActiva) {
            try {
                $checker = app(\App\Services\Whatsapp\Ventana24hChecker::class);
                $this->ventana24hAbierta = $checker->abierta($conversacionActiva);
                $this->ventana24hMinutosRestantes = $checker->minutosRestantes($conversacionActiva);
            } catch (\Throwable $e) { /* silent */ }
        }

        // 🟢 Respuestas rápidas del tenant
        $respuestasRapidas = collect();
        try {
            $respuestasRapidas = \App\Models\RespuestaRapida::where('activa', true)
                ->orderBy('orden')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) { /* tabla puede no existir aún */ }

        // 📊 Contadores para los chips estilo WhatsApp
        $totalNoLeidos = ConversacionWhatsapp::query()
            ->where(fn ($q) => $q->where('no_leidos', '>', 0)->orWhere('marcada_no_leida', true))
            ->count();
        $totalFavoritos = ConversacionWhatsapp::query()->whereNotNull('fijada_at')->count();

        // 👥 Grupos de clientes (para los chips del chat) — fijados primero
        $gruposChat = \App\Models\GrupoCliente::withCount('clientes')
            ->orderByRaw('fijado_at IS NULL, fijado_at DESC')
            ->orderBy('nombre')
            ->get();

        // 👥 Clientes para el modal "Crear lista" (chats recientes o búsqueda)
        $clientesParaLista = $this->modalCrearGrupo ? $this->clientesParaLista : collect();

        return view('livewire.chat.index', compact(
            'conversaciones',
            'conversacionActiva',
            'pedidoEstado',
            'promptInspeccion',
            'plantillasMetaAprobadas',
            'plantillaChatSeleccionada',
            'respuestasRapidas',
            'totalNoLeidos',
            'totalFavoritos',
            'gruposChat',
            'clientesParaLista'
        ))->layout('layouts.app');
    }

    /** 🟢 Insertar el texto de una respuesta rápida en el input */
    public function usarRespuestaRapida(int $id): void
    {
        try {
            $resp = \App\Models\RespuestaRapida::find($id);
            if (!$resp) return;
            $this->nuevoMensaje = $resp->texto;
        } catch (\Throwable $e) { /* silent */ }
    }

    /**
     * 🟢 Envía una plantilla Meta aprobada desde el chat manual.
     * Esto sirve para reabrir conversaciones que pasaron la ventana 24h
     * (Meta solo permite plantillas fuera de ese rango).
     */
    public function enviarPlantilla(): void
    {
        if (!$this->conversacionActivaId || !$this->plantillaChatId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) return;

        $tpl = \App\Models\MetaWhatsappPlantilla::find($this->plantillaChatId);
        if (!$tpl) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Plantilla no encontrada.']);
            return;
        }

        // Construir array posicional de variables a partir del input del operador
        $varsOrdenadas = [];
        for ($i = 1; $i <= ($tpl->num_variables ?? 0); $i++) {
            $varsOrdenadas[] = (string) ($this->plantillaChatVars[$i] ?? '');
        }

        $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
            ->enviarPlantilla(
                $conv->telefono_normalizado,
                $tpl->nombre,
                $varsOrdenadas,
                $conv->tenant_id,
                $tpl->idioma ?: 'es'
            );

        if (!$ok) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar la plantilla.']);
            return;
        }

        // Persistir mensaje en conversación renderizando body con variables
        $body = $tpl->body_preview ?: ('[plantilla:' . $tpl->nombre . ']');
        foreach ($varsOrdenadas as $i => $valor) {
            $body = str_replace(['{{' . ($i + 1) . '}}', '{{ ' . ($i + 1) . ' }}'], $valor, $body);
        }
        try {
            app(\App\Services\ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $body,
                ['meta' => [
                    'enviado_por_humano' => true,
                    'usuario_id'         => auth()->id(),
                    'origen'             => 'plantilla_meta',
                    'plantilla'          => $tpl->nombre,
                    'plantilla_idioma'   => $tpl->idioma,
                ]]
            );
        } catch (\Throwable $e) {
            Log::warning('No se persistió mensaje plantilla en conversación: ' . $e->getMessage());
        }

        $this->plantillaChatId = null;
        $this->plantillaChatVars = [];
        $this->ventana24hAbierta = true; // la plantilla "reabre" la sesión
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Plantilla enviada.']);
        $this->dispatch('mensaje-enviado');
    }
}
