<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingEnvio extends Model
{
    protected $table = 'saas_billing_envios';

    public const TIPO_FACTURA       = 'factura';
    public const TIPO_RECORDATORIO  = 'recordatorio';
    public const TIPO_SUSPENDIDO    = 'suspendido';

    public const TIPOS = [
        self::TIPO_FACTURA      => '🧾 Factura nueva',
        self::TIPO_RECORDATORIO => '⏰ Recordatorio',
        self::TIPO_SUSPENDIDO   => '🚫 Suspendido',
    ];

    public const ETAPAS = [
        'factura'      => '🧾 Factura nueva',
        'preaviso'     => '📅 Preaviso (-3d)',
        'vence_hoy'    => '⏰ Vence hoy',
        'vencio_ayer'  => '⚠️ Venció ayer',
        'urgencia'     => '🚨 Urgencia (+3d)',
        'suspendido'   => '🚫 Suspendido',
    ];

    protected $fillable = [
        'tenant_id', 'pago_id', 'suscripcion_id',
        'tipo', 'etapa', 'canal', 'telefono',
        'monto', 'moneda',
        'ok', 'intentos', 'ultimo_intento_at',
        'mensaje', 'link_pago', 'error',
    ];

    protected $casts = [
        'ok'                => 'boolean',
        'monto'             => 'decimal:2',
        'intentos'          => 'integer',
        'ultimo_intento_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function etapaLabel(): string
    {
        return self::ETAPAS[$this->etapa ?? ''] ?? '—';
    }

    /**
     * 🔁 Reintenta el envío usando los mismos datos. Incrementa contador.
     * Devuelve true si esta vez salió OK.
     */
    public function reintentar(): bool
    {
        if ($this->ok) return true; // ya estaba OK

        $tenant = $this->tenant;
        if (!$tenant) {
            $this->update([
                'intentos'          => $this->intentos + 1,
                'ultimo_intento_at' => now(),
                'error'             => 'Tenant fue eliminado o no existe',
            ]);
            return false;
        }

        $tel = $this->telefono ?: $tenant->contacto_telefono;
        if (!$tel) {
            $this->update([
                'intentos'          => $this->intentos + 1,
                'ultimo_intento_at' => now(),
                'error'             => 'Tenant sin contacto_telefono. Edita el tenant en /admin/tenants',
            ]);
            return false;
        }

        // Validar formato
        $telLimpio = preg_replace('/[^0-9]/', '', $tel);
        if (strlen($telLimpio) < 10) {
            $this->update([
                'intentos'          => $this->intentos + 1,
                'ultimo_intento_at' => now(),
                'error'             => "Teléfono '{$tel}' inválido. Mínimo 10 dígitos (debe incluir código país).",
            ]);
            return false;
        }

        // Setear tenant context para que el sender resuelva sus credenciales
        try {
            app(\App\Services\TenantManager::class)->set($tenant);
        } catch (\Throwable $e) { /* ignore */ }

        $okEnvio = false;
        $errorMsg = null;

        try {
            $okEnvio = (bool) app(\App\Services\WhatsappSenderService::class)
                ->enviarTexto($telLimpio, $this->mensaje, $tenant->id);

            if (!$okEnvio) {
                // Capturar la última línea de log relevante para dar pista
                $errorMsg = $this->ultimoErrorDelLog($tenant->id);
                if (!$errorMsg) {
                    $proveedor = $tenant->proveedorWhatsappResuelto();
                    $errorMsg = "Envío rechazado por proveedor '{$proveedor}'. Posibles causas: "
                        . ($proveedor === 'meta'
                            ? 'token Meta caducado, número fuera de ventana 24h, o número no registrado en WABA.'
                            : 'TecnoByteApp sin sesión activa o credenciales inválidas.');
                }
            }
        } catch (\Throwable $e) {
            $errorMsg = 'Excepción: ' . mb_substr($e->getMessage(), 0, 350);
        }

        $this->update([
            'ok'                => $okEnvio,
            'telefono'          => $telLimpio,
            'intentos'          => $this->intentos + 1,
            'ultimo_intento_at' => now(),
            'error'             => $okEnvio ? null : $errorMsg,
        ]);

        return $okEnvio;
    }

    /** Busca la última línea de error en laravel.log que mencione al tenant. */
    private function ultimoErrorDelLog(int $tenantId): ?string
    {
        $logPath = storage_path('logs/laravel.log');
        if (!is_readable($logPath)) return null;

        try {
            $cmd = sprintf(
                'tail -n 200 %s 2>/dev/null | grep -iE "(WA Sender|Meta WA|login fall|tenant_id.{0,5}%d)" | tail -1',
                escapeshellarg($logPath),
                $tenantId
            );
            $linea = trim((string) @shell_exec($cmd));
            if ($linea === '') return null;
            // Quitar timestamp prefix y dejar solo el mensaje
            $linea = preg_replace('/^\[[^\]]+\]\s*\S+\s*/', '', $linea);
            return mb_substr($linea, 0, 400);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
