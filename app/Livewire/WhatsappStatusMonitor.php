<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class WhatsappStatusMonitor extends Component
{
    public string $status = 'checking';
    public string $statusLabel = 'Verificando...';
    public string $phoneNumber = '';
    public string $lastChecked = '';
    public ?string $qrCode = null;
    public ?int $connectionId = null;
    public bool $showQr = false;
    public ?string $disconnectReason = null;

    private string $cacheEstadoAnterior = 'whatsapp_monitor_estado_anterior';
    private string $cacheAlertaDesconexion = 'alerta_whatsapp_desconexion_enviada';

    public function mount(): void
    {
        $this->verificarEstado();
    }

    public function verificarEstado(): void
    {
        try {
            $token = $this->obtenerTokenForzado();

            if (!$token) {
                $this->setEstado('error', 'Sin token — verifica credenciales en .env');
                return;
            }

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->get('https://wa-api.tecnobyteapp.com:1422/whatsapp/');

            if ($response->status() === 401) {
                Cache::forget('whatsapp_api_token');
                $token = $this->loginWhatsapp(force: true);

                if (!$token) {
                    $this->setEstado('error', 'Sesión expirada — no se pudo renovar');
                    return;
                }

                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(15)
                    ->get('https://wa-api.tecnobyteapp.com:1422/whatsapp/');
            }

            if ($response->failed()) {
                Log::warning('⚠️ Monitor WA: API falló', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                $this->setEstado('error', "Error API ({$response->status()})");
                return;
            }

            $whatsapps = collect($response->json('whatsapps', []));

            if ($whatsapps->isEmpty()) {
                $this->setEstado('error', 'Sin conexiones configuradas en la API');
                return;
            }

            // ── Conexión correspondiente al TENANT actual ────────────────
            // Preferimos ConfiguracionBot::connection_id_default. Si la
            // lectura falla (p.ej. permisos de caché en producción) o no
            // está configurada, hacemos fallback al primer isDefault del
            // listado para no bloquear la UI.
            $tenantConnId = null;
            try {
                $tenantConnId = (int) (\App\Models\ConfiguracionBot::actual()->connection_id_default ?? 0) ?: null;
            } catch (\Throwable $e) {
                Log::warning('WA monitor: no se pudo leer connection_id_default: ' . $e->getMessage());
            }

            $conexion = null;
            if ($tenantConnId) {
                $conexion = $whatsapps->firstWhere('id', $tenantConnId);
            }

            if (!$conexion) {
                // Fallback: primera con isDefault y CONNECTED, o primera con isDefault, o la primera
                $conexion = $whatsapps->first(
                    fn ($w) => strtoupper($w['status'] ?? '') === 'CONNECTED' && (bool) ($w['isDefault'] ?? false)
                ) ?? $whatsapps->first(fn ($w) => !empty($w['isDefault']))
                  ?? $whatsapps->first();
            }

            if (!$conexion) {
                $this->setEstado('error', 'Sin conexión disponible para este tenant');
                return;
            }

            $estadoApi = strtoupper($conexion['status'] ?? 'UNKNOWN');

            $this->connectionId = $conexion['id'] ?? null;
            $this->phoneNumber = $conexion['phoneNumber']
                ?? $conexion['profileName']
                ?? $conexion['name']
                ?? '';
            $this->lastChecked = now()->format('h:i:s a');

            $qrListado = $conexion['qrcode'] ?? null;
            if (!blank($qrListado)) {
                $this->qrCode = $qrListado;
            }

            Log::info('📡 Monitor WA estado', [
                'estadoApi'    => $estadoApi,
                'connectionId' => $this->connectionId,
                'phoneNumber'  => $this->phoneNumber,
                'tieneQr'      => !empty($this->qrCode),
            ]);

            $estadoAnterior = Cache::get($this->cacheEstadoAnterior);

            if ($estadoApi === 'CONNECTED') {
                $this->procesarConectado($estadoAnterior);
                return;
            }

            if (in_array($estadoApi, ['QRCODE', 'QR_CODE', 'PAIRING'])) {
                $this->procesarEstadoQr($estadoApi);
                return;
            }

            if (in_array($estadoApi, ['TIMEOUT', 'NOT_CONNECTED', 'DISCONNECTED'])) {
                $this->procesarDesconectado($estadoApi);
                return;
            }

            $this->procesarDesconectado($estadoApi);
        } catch (\Throwable $e) {
            Log::error('❌ Monitor WA excepción', [
                'error' => $e->getMessage(),
            ]);

            $this->setEstado('error', 'Error inesperado — ver logs');
        }
    }

    private function procesarConectado(?string $estadoAnterior): void
    {
        $this->status = 'connected';
        $this->statusLabel = 'Conectado';
        $this->showQr = false;
        $this->qrCode = null;
        $this->disconnectReason = null;

        if ($estadoAnterior && $estadoAnterior !== 'CONNECTED') {
            Cache::forget($this->cacheAlertaDesconexion);
        }

        Cache::put($this->cacheEstadoAnterior, 'CONNECTED', now()->addHours(2));
    }

    private function procesarEstadoQr(string $estadoApi): void
    {
        $this->status = 'qrcode';
        $this->statusLabel = $estadoApi === 'PAIRING'
            ? 'Emparejando / esperando QR'
            : 'Escanea el nuevo QR';

        $this->disconnectReason = 'requiere escaneo QR';
        $this->showQr = true;

        if (blank($this->qrCode) && $this->connectionId) {
            $this->solicitarNuevoQrInterno();
        }

        if (!blank($this->qrCode)) {
            $this->status = 'qrcode';
            $this->statusLabel = 'Escanea el nuevo QR';
            $this->showQr = true;
        }

        $this->enviarAlertaSiNecesario($estadoApi);
        Cache::put($this->cacheEstadoAnterior, $estadoApi, now()->addHours(2));
    }

    private function procesarDesconectado(string $estadoApi): void
    {
        $this->status = 'disconnected';
        $this->statusLabel = 'Generando QR...';
        $this->disconnectReason = 'la sesión se desconectó, intentando generar un nuevo QR';
        $this->showQr = false;
        $this->qrCode = null;

        $this->intentarGenerarQr();

        if (!blank($this->qrCode)) {
            $this->status = 'qrcode';
            $this->statusLabel = 'Escanea el nuevo QR';
            $this->disconnectReason = 'QR generado automáticamente';
            $this->showQr = true;
        } else {
            $this->status = match ($estadoApi) {
                'TIMEOUT'       => 'timeout',
                'NOT_CONNECTED' => 'not_connected',
                default         => 'disconnected',
            };

            $this->statusLabel = 'Desconectado';
            $this->disconnectReason = 'la API no devolvió un QR nuevo todavía';
        }

        $this->enviarAlertaSiNecesario($estadoApi);
        Cache::put($this->cacheEstadoAnterior, $estadoApi, now()->addHours(2));
    }

    /**
     * Fuerza la regeneración del QR cerrando sesión en TecnoByteApp.
     * Útil cuando la sesión está "pegada" y la API no devuelve QR nuevo.
     */
    public function forzarReconexion(): void
    {
        if (!$this->connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay conexión asignada a este tenant.']);
            return;
        }

        try {
            $token = $this->obtenerTokenForzado();
            if (!$token) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo autenticar contra la API de WhatsApp.']);
                return;
            }

            // Intentamos varios endpoints conocidos del wrapper whatsapp-web.js
            $candidatos = [
                ['POST', "/whatsapp/{$this->connectionId}/logout"],
                ['POST', "/whatsapp/{$this->connectionId}/disconnect"],
                ['POST', "/whatsapp/{$this->connectionId}/restart"],
            ];

            $ok = false;
            foreach ($candidatos as [$verb, $path]) {
                $req = Http::withoutVerifying()->withToken($token)->timeout(20);
                $resp = $verb === 'POST' ? $req->post("https://wa-api.tecnobyteapp.com:1422{$path}") : $req->get("https://wa-api.tecnobyteapp.com:1422{$path}");
                Log::info('🔁 forzarReconexion intento', ['path' => $path, 'status' => $resp->status()]);
                if ($resp->successful()) { $ok = true; break; }
            }

            if (!$ok) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'La API no aceptó la reconexión forzada. Espera 30s y reintenta.']);
                return;
            }

            // Esperar un momento y refrescar
            usleep(1500000);
            $this->verificarEstado();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Reconexión forzada. Espera el nuevo QR…']);
        } catch (\Throwable $e) {
            Log::error('❌ forzarReconexion: ' . $e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function solicitarNuevoQr(): void
    {
        $this->status = 'disconnected';
        $this->statusLabel = 'Generando QR...';
        $this->disconnectReason = 'solicitando nuevo QR';
        $this->qrCode = null;
        $this->showQr = false;

        $this->intentarGenerarQr();

        if (!blank($this->qrCode)) {
            $this->status = 'qrcode';
            $this->statusLabel = 'Escanea el nuevo QR';
            $this->disconnectReason = 'QR listo para escanear';
            $this->showQr = true;
            return;
        }

        $this->status = 'disconnected';
        $this->statusLabel = 'Desconectado';
        $this->disconnectReason = 'no fue posible obtener el QR desde la API';
    }

    private function intentarGenerarQr(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->solicitarNuevoQrInterno();

            if (!blank($this->qrCode)) {
                return;
            }

            usleep(800000);
        }
    }

    private function solicitarNuevoQrInterno(): void
    {
        if (!$this->connectionId) {
            Log::warning('⚠️ No se puede generar QR: connectionId vacío');
            return;
        }

        try {
            $token = $this->obtenerTokenForzado();

            if (!$token) {
                return;
            }

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->get("https://wa-api.tecnobyteapp.com:1422/whatsapp/{$this->connectionId}");

            Log::info('🔄 Refresco QR - detalle conexión', [
                'connectionId' => $this->connectionId,
                'httpStatus'   => $response->status(),
                'body'         => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $conexion = $data['whatsapp'] ?? $data ?? null;

                if ($conexion && !blank($conexion['qrcode'] ?? null)) {
                    $this->qrCode = $conexion['qrcode'];
                    return;
                }
            }

            $retry = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->get('https://wa-api.tecnobyteapp.com:1422/whatsapp/');

            Log::info('🔁 Reconsulta listado WA para QR', [
                'status' => $retry->status(),
                'body'   => $retry->body(),
            ]);

            if ($retry->successful()) {
                $whatsapps = collect($retry->json('whatsapps', []));
                $conexion = $whatsapps->firstWhere('id', $this->connectionId);

                if ($conexion && !blank($conexion['qrcode'] ?? null)) {
                    $this->qrCode = $conexion['qrcode'];
                }
            }
        } catch (\Throwable $e) {
            Log::error('❌ Error refrescando QR', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function setEstado(string $status, string $label): void
    {
        $this->status = $status;
        $this->statusLabel = $label;
        $this->lastChecked = now()->format('h:i:s a');
    }

    private function obtenerTokenForzado(): ?string
    {
        return Cache::get('whatsapp_api_token') ?? $this->loginWhatsapp();
    }

    private function loginWhatsapp(bool $force = false): ?string
    {
        $cacheKey = 'whatsapp_api_token';

        if ($force) {
            Cache::forget($cacheKey);
        } elseif ($token = Cache::get($cacheKey)) {
            return $token;
        }

        try {
            $email = env('WHATSAPP_API_EMAIL');
            $password = env('WHATSAPP_API_PASSWORD');

            if (!$email || !$password) {
                Log::error('❌ Monitor WA: WHATSAPP_API_EMAIL o WHATSAPP_API_PASSWORD no definidos en .env');
                return null;
            }

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->post('https://wa-api.tecnobyteapp.com:1422/auth/login', [
                    'email'    => $email,
                    'password' => $password,
                ]);

            if ($response->failed()) {
                Log::error('❌ Monitor WA: Login falló', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $token = $response->json('token');

            if (!$token) {
                Log::error('❌ Monitor WA: Login sin token', [
                    'body' => $response->body(),
                ]);
                return null;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(20));

            return $token;
        } catch (\Throwable $e) {
            Log::error('❌ Monitor WA: Excepción login', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function enviarAlertaSiNecesario(string $estadoApi): void
    {
        $minutos = (int) env('WHATSAPP_ALERTA_MINUTOS', 30);
        if ($minutos <= 0) {
            $minutos = 30;
        }

        if (!Cache::has($this->cacheAlertaDesconexion)) {
            Cache::put($this->cacheAlertaDesconexion, true, now()->addMinutes($minutos));
            $this->enviarCorreoAlerta($estadoApi);
        }
    }

    private function enviarCorreoAlerta(string $estadoApi): void
    {
        try {
            $destinatarios = collect(explode(',', (string) env('ALERTAS_TECNICAS_EMAILS', '')))
                ->map(fn ($e) => trim($e))
                ->filter()
                ->values()
                ->all();

            if (empty($destinatarios)) {
                return;
            }

            $appNombre = env('APP_NOMBRE_ALERTAS', config('app.name', 'Plataforma'));

            Mail::raw(
                implode("\n", [
                    "🚨 ALERTA DE DESCONEXIÓN DE WHATSAPP",
                    "",
                    "Aplicación      : {$appNombre}",
                    "Estado detectado: {$estadoApi}",
                    "Motivo          : " . ($this->disconnectReason ?: 'No identificado'),
                    "Connection ID   : " . ($this->connectionId ?? 'N/A'),
                    "Número          : " . ($this->phoneNumber ?: 'N/A'),
                    "Fecha y hora    : " . now()->format('d/m/Y H:i:s'),
                    "",
                    "Acción requerida:",
                    "Escanea el nuevo QR en la plataforma.",
                ]),
                fn ($m) => $m
                    ->to($destinatarios)
                    ->subject("🚨 ALERTA CRÍTICA: WhatsApp DESCONECTADO - {$appNombre}")
            );
        } catch (\Throwable $e) {
            Log::error('❌ Error enviando correo alerta WA', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.whatsapp-status-monitor');
    }
}