<?php

namespace App\Services\Sap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de bajo nivel para SAP Business One Service Layer (OData v4/b1s/v1).
 *
 * Responsabilidades:
 *   - Login y manejo/cacheo de la sesión (cookie B1SESSION).
 *   - Reintento automático cuando la sesión expira (error 301 / HTTP 401).
 *   - GET genérico a recursos OData (Quotations, Orders, sml.svc/*, etc).
 *
 * Es AGNÓSTICO al transporte: si config('sap.connection.mode') === 'bridge',
 * las llamadas salen contra el agente/puente en la red autorizada en vez de
 * ir directo al Service Layer. La forma de la respuesta es la misma para el
 * resto de la app.
 *
 * Reutiliza el patrón probado del proyecto COTIZADOR (login → B1SESSION →
 * Cookie + ROUTEID=.node2 → verify:false → refresh en timeout).
 */
class SapServiceLayerClient
{
    private string $mode;
    private string $baseUrl;
    private string $company;
    private string $username;
    private string $password;
    private int    $timeout;
    private string $bridgeUrl;
    private string $bridgeToken;
    private string $cacheKey;

    public function __construct(?array $cfg = null)
    {
        $cfg = $cfg ?: (array) config('sap.connection', []);

        $this->mode        = $cfg['mode'] ?? 'direct';
        $this->baseUrl     = rtrim((string) ($cfg['url'] ?? ''), '/');
        $this->company     = (string) ($cfg['database'] ?? '');
        $this->username    = (string) ($cfg['username'] ?? '');
        $this->password    = (string) ($cfg['password'] ?? '');
        $this->timeout     = (int)    ($cfg['timeout'] ?? 30);
        $this->bridgeUrl   = rtrim((string) ($cfg['bridge_url'] ?? ''), '/');
        $this->bridgeToken = (string) ($cfg['bridge_token'] ?? '');

        // La sesión se cachea por (url + company + user) para soportar
        // varias conexiones/tenants sin colisionar.
        $this->cacheKey = 'sap_sl_session_' . md5($this->baseUrl . '|' . $this->company . '|' . $this->username);
    }

    /**
     * Construye un cliente con la conexión del TENANT indicado. Si el tenant no
     * tiene conexión propia en `sap_tenant_configs`, usa la global de config
     * (útil para pruebas locales de un solo cliente).
     */
    public static function paraTenant(?int $tenantId): self
    {
        $cfg = \App\Models\SapTenantConfig::paraTenant($tenantId)?->conexion();
        return new self($cfg); // null → config('sap.connection')
    }

    /* ─────────────────────────── API pública ─────────────────────────── */

    /**
     * GET a un recurso OData. Ej:
     *   $sap->get("Quotations?\$select=DocNum,DocTotal&\$top=5")
     *   $sap->get("Orders(123)")
     *   $sap->get("sml.svc/ESTADOPEDIDO?\$filter=DocEntry eq 123")
     *
     * @return array{ok:bool, value:array, raw?:array, error?:string, detalle?:mixed}
     */
    public function get(string $resource, int $reintentos = 1): array
    {
        if ($this->mode === 'bridge') {
            return $this->getViaBridge($resource);
        }
        return $this->getDirecto($resource, $reintentos);
    }

    /** Prueba de conexión (login forzado). Útil para diagnóstico. */
    public function ping(): array
    {
        if ($this->mode === 'bridge') {
            $r = $this->getViaBridge('ping');
            return ['modo' => 'bridge', 'conecta' => (bool) ($r['ok'] ?? false), 'url' => $this->bridgeUrl];
        }
        $sid = $this->login();
        return ['modo' => 'direct', 'conecta' => (bool) $sid, 'company' => $this->company, 'url' => $this->baseUrl];
    }

    /* ─────────────────────────── Modo directo ────────────────────────── */

    /** Devuelve un B1SESSION vigente (cacheado), logueando si hace falta. */
    private function sessionId(): ?string
    {
        return Cache::get($this->cacheKey) ?: $this->login();
    }

    /** POST /Login. Guarda el token en cache y lo devuelve. */
    private function login(): ?string
    {
        if ($this->baseUrl === '' || $this->company === '' || $this->username === '') {
            Log::warning('SAP SL: configuración incompleta (url/company/username).');
            return null;
        }

        try {
            $resp = Http::withoutVerifying()
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl . '/b1s/v1/Login', [
                    'CompanyDB' => $this->company,
                    'UserName'  => $this->username,
                    'Password'  => $this->password,
                ]);

            if ($resp->successful() && $resp->json('SessionId')) {
                $sid     = (string) $resp->json('SessionId');
                $minutos = (int) ($resp->json('SessionTimeout') ?? 30);
                // Renovamos un poco antes de que expire realmente.
                Cache::put($this->cacheKey, $sid, now()->addMinutes(max(1, $minutos - 5)));
                return $sid;
            }

            Log::warning('SAP SL login falló', [
                'status' => $resp->status(),
                'body'   => mb_substr($resp->body(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            Log::error('SAP SL login excepción: ' . $e->getMessage());
        }

        return null;
    }

    private function getDirecto(string $resource, int $reintentos): array
    {
        $sid = $this->sessionId();
        if (!$sid) {
            return ['ok' => false, 'error' => 'sin_sesion_sap', 'value' => []];
        }

        try {
            $resp = Http::withoutVerifying()
                ->timeout($this->timeout)
                ->withHeaders([
                    'Cookie'       => "B1SESSION={$sid}; ROUTEID=.node2",
                    'Content-Type' => 'application/json',
                ])
                ->get($this->baseUrl . '/b1s/v1/' . ltrim($resource, '/'));

            if ($resp->successful()) {
                $json = $resp->json() ?? [];
                return ['ok' => true, 'value' => $json['value'] ?? $json, 'raw' => $json];
            }

            // Sesión expirada → re-login + reintento (301 en el body, o 401).
            $codeSap = $resp->json('error.code');
            if ($reintentos > 0 && ($resp->status() === 401 || (int) $codeSap === 301)) {
                Cache::forget($this->cacheKey);
                $this->login();
                return $this->getDirecto($resource, $reintentos - 1);
            }

            Log::warning('SAP SL GET falló', [
                'recurso' => $resource,
                'status'  => $resp->status(),
                'body'    => mb_substr($resp->body(), 0, 300),
            ]);

            return [
                'ok'      => false,
                'error'   => 'http_' . $resp->status(),
                'detalle' => $resp->json('error.message.value') ?? mb_substr($resp->body(), 0, 300),
                'value'   => [],
            ];
        } catch (\Throwable $e) {
            Log::error('SAP SL GET excepción: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'excepcion', 'detalle' => $e->getMessage(), 'value' => []];
        }
    }

    /* ─────────────────────────── Modo puente ─────────────────────────── */

    /**
     * En modo 'bridge', el agente de la red autorizada expone el mismo GET
     * OData bajo POST {bridge_url}/sap/get con { resource }. Devuelve la
     * misma forma { ok, value }.
     */
    private function getViaBridge(string $resource): array
    {
        if ($this->bridgeUrl === '') {
            return ['ok' => false, 'error' => 'bridge_sin_url', 'value' => []];
        }

        try {
            $resp = Http::timeout($this->timeout)
                ->withHeaders(['X-Bridge-Token' => $this->bridgeToken])
                ->acceptJson()
                ->post($this->bridgeUrl . '/sap/get', ['resource' => $resource]);

            if ($resp->successful()) {
                $json = $resp->json() ?? [];
                return ['ok' => true, 'value' => $json['value'] ?? $json, 'raw' => $json];
            }

            return ['ok' => false, 'error' => 'bridge_http_' . $resp->status(), 'value' => []];
        } catch (\Throwable $e) {
            Log::error('SAP SL bridge excepción: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'bridge_excepcion', 'detalle' => $e->getMessage(), 'value' => []];
        }
    }
}
