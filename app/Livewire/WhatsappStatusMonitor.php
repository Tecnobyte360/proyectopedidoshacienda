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

            $conexion = $whatsapps->first(
                fn ($w) =>
                    strtoupper($w['status'] ?? '') === 'CONNECTED'
                    && (bool) ($w['isDefault'] ?? false)
            ) ?? $whatsapps->first(
                fn ($w) => !empty($w['isDefault'])
            ) ?? $whatsapps->first();

            $estadoApi = strtoupper($conexion['status'] ?? 'UNKNOWN');

            $this->connectionId = $conexion['id'] ?? null;
            $this->phoneNumber = $conexion['phoneNumber']
                ?? $conexion['profileName']
                ?? $conexion['name']
                ?? '';
            $this->lastChecked = now()->format('h:i a');

            $qrDesdeListado = $conexion['qrcode'] ?? null;
            if (!blank($qrDesdeListado)) {
                $this->qrCode = $qrDesdeListado;
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

            Log::info('✅ WhatsApp reconectado', [
                'connectionId' => $this->connectionId,
                'phoneNumber'  => $this->phoneNumber,
            ]);
        }

        Cache::put($this->cacheEstadoAnterior, 'CONNECTED', now()->addHours(2));
    }

    private function procesarEstadoQr(string $estadoApi): void
    {
        $this->status = 'qrcode';
        $this->statusLabel = $estadoApi === 'PAIRING'
            ? 'Emparejando / esperando QR'
            : 'Esperando escaneo QR';

        $this->disconnectReason = 'requiere escaneo QR';
        $this->showQr = true;

        // Si la API reporta estado QR pero no mandó el código,
        // lo intentamos recuperar automáticamente.
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
        $this->status = match ($estadoApi) {
            'TIMEOUT'       => 'timeout',
            'NOT_CONNECTED' => 'not_connected',
            default         => 'disconnected',
        };

        $this->statusLabel = match ($estadoApi) {
            'TIMEOUT'       => 'Tiempo agotado',
            'NOT_CONNECTED' => 'No conectado',
            'DISCONNECTED'  => 'Desconectado',
            default         => "Sin conexión ({$estadoApi})",
        };

        $this->disconnectReason = match ($estadoApi) {
            'TIMEOUT'       => 'expiró la sesión o el QR',
            'NOT_CONNECTED' => 'no hay sesión activa',
            'DISCONNECTED'  => 'desconexión de la sesión',
            default         => "estado {$estadoApi}",
        };

        $this->showQr = false;

        // Muy importante:
        // limpiamos QR viejo para forzar refresco real
        $this->qrCode = null;

        if ($this->connectionId) {
            $this->solicitarNuevoQrInterno();
        }

        if (!blank($this->qrCode)) {
            $this->status = 'qrcode';
            $this->statusLabel = 'Escanea el nuevo QR';
            $this->disconnectReason = 'la sesión se desconectó, escanea para reconectar';
            $this->showQr = true;
        }

        $this->enviarAlertaSiNecesario($estadoApi);
        Cache::put($this->cacheEstadoAnterior, $estadoApi, now()->addHours(2));
    }

    private function setEstado(string $status, string $label): void
    {
        $this->status = $status;
        $this->statusLabel = $label;
        $this->lastChecked = now()->format('h:i a');
    }

    public function solicitarNuevoQr(): void
    {
        $this->qrCode = null;
        $this->showQr = false;

        $this->solicitarNuevoQrInterno();

        if (!blank($this->qrCode)) {
            $this->status = 'qrcode';
            $this->statusLabel = 'Escanea el nuevo QR';
            $this->disconnectReason = 'QR listo para escanear';
            $this->showQr = true;
            return;
        }

        $this->verificarEstado();
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

            // Consulta detalle de la conexión
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->get("https://wa-api.tecnobyteapp.com:1422/whatsapp/{$this->connectionId}");

            Log::info('🔄 Refresco QR', [
                'connectionId' => $this->connectionId,
                'httpStatus'   => $response->status(),
                'body'         => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $conexion = $data['whatsapp'] ?? $data ?? null;

                if ($conexion && !blank($conexion['qrcode'] ?? null)) {
                    $this->qrCode = $conexion['qrcode'];
                }
            }

            // Si aún no hay QR, volvemos a consultar el listado general
            if (blank($this->qrCode)) {
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
            }

            if (!blank($this->qrCode)) {
                $this->showQr = true;
                $this->status = 'qrcode';
                $this->statusLabel = 'Escanea el nuevo QR';
            } else {
                Log::warning('⚠️ No fue posible obtener QR nuevo', [
                    'connectionId' => $this->connectionId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('❌ Error refrescando QR', [
                'error' => $e->getMessage(),
            ]);
        }
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

            Log::info('🔐 Login WA intento', [
                'status' => $response->status(),
                'body'   => $response->body(),
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
            Log::info('✅ Monitor WA: Token obtenido');

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
            Cache::put(
                $this->cacheAlertaDesconexion,
                true,
                now()->addMinutes($minutos)
            );

            Log::warning('🚨 ALERTA WHATSAPP DESCONEXION', [
                'estado'               => $estadoApi,
                'connectionId'         => $this->connectionId,
                'phoneNumber'          => $this->phoneNumber,
                'minutos_configurados' => $minutos,
                'motivo'               => $this->disconnectReason,
            ]);

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
                Log::warning('⚠️ Monitor WA: Sin correos en ALERTAS_TECNICAS_EMAILS');
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

            Log::info('📧 Alerta correo enviada', [
                'estado'        => $estadoApi,
                'destinatarios' => $destinatarios,
            ]);
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