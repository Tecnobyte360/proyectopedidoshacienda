<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente para la API DNS de Hostinger.
 *
 * Documentación: https://developers.hostinger.com/
 *
 * Endpoints utilizados:
 *   GET  /api/dns/v1/zones/{domain}              -> Lista todos los registros de la zona
 *   PUT  /api/dns/v1/zones/{domain}              -> Reemplaza/actualiza registros (upsert)
 *   DELETE /api/dns/v1/zones/{domain}/records    -> Elimina registros específicos
 *
 * Uso típico:
 *   $svc = app(HostingerDnsService::class);
 *   $svc->crearSubdominio('la-hacienda');   // crea la-hacienda.tecnobyte360.com -> IP del VPS
 *   $svc->existeSubdominio('la-hacienda');  // bool
 *   $svc->eliminarSubdominio('la-hacienda');
 */
class HostingerDnsService
{
    private string $baseUrl = 'https://developers.hostinger.com/api/dns/v1';
    private string $apiKey;
    private string $domain;
    private ?string $serverIp;
    private int $ttl;

    public function __construct()
    {
        $this->apiKey   = (string) config('services.hostinger.api_key');
        $this->domain   = (string) config('services.hostinger.domain', 'tecnobyte360.com');
        $this->serverIp = config('services.hostinger.server_ip');
        $this->ttl      = (int) config('services.hostinger.ttl', 300);

        if (!$this->apiKey) {
            throw new RuntimeException('HOSTINGER_API_KEY no está configurada en .env');
        }
    }

    /**
     * Lista todos los registros DNS de la zona.
     */
    public function listarRegistros(): array
    {
        $resp = $this->http()->get("{$this->baseUrl}/zones/{$this->domain}");

        if (!$resp->successful()) {
            throw new RuntimeException("Hostinger API error ({$resp->status()}): " . $resp->body());
        }

        return $resp->json() ?? [];
    }

    /**
     * Verifica si un subdominio ya tiene registro A.
     */
    public function existeSubdominio(string $slug): bool
    {
        $name = $this->normalizarName($slug);
        try {
            $registros = $this->listarRegistros();
        } catch (\Throwable $e) {
            Log::warning('Hostinger listarRegistros falló: ' . $e->getMessage());
            return false;
        }

        foreach ($registros as $r) {
            if (($r['type'] ?? null) === 'A' && ($r['name'] ?? null) === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Crea (o actualiza) el registro A para {slug}.{domain} apuntando al VPS.
     *
     * @return array Información del registro creado.
     */
    public function crearSubdominio(string $slug, ?string $ip = null): array
    {
        $ip = $ip ?: $this->serverIp;
        if (!$ip) {
            throw new RuntimeException('HOSTINGER_SERVER_IP no está configurada en .env');
        }

        $name = $this->normalizarName($slug);

        // PUT /zones/{domain} con { "overwrite": true, "zone": [ ... ] }
        // Hostinger acepta el upsert por (type, name).
        $payload = [
            'overwrite' => true,
            'zone' => [[
                'name'    => $name,
                'type'    => 'A',
                'ttl'     => $this->ttl,
                'records' => [[
                    'content' => $ip,
                ]],
            ]],
        ];

        $resp = $this->http()->put("{$this->baseUrl}/zones/{$this->domain}", $payload);

        if (!$resp->successful()) {
            throw new RuntimeException(
                "Hostinger API error al crear DNS '{$name}.{$this->domain}' ({$resp->status()}): " . $resp->body()
            );
        }

        Log::info("Hostinger DNS creado: {$name}.{$this->domain} -> {$ip}");

        return [
            'name'   => $name,
            'fqdn'   => "{$name}.{$this->domain}",
            'ip'     => $ip,
            'ttl'    => $this->ttl,
            'response' => $resp->json(),
        ];
    }

    /**
     * Elimina el registro A del subdominio.
     */
    public function eliminarSubdominio(string $slug): bool
    {
        $name = $this->normalizarName($slug);

        $payload = [
            'filters' => [[
                'type' => 'A',
                'name' => $name,
            ]],
        ];

        $resp = $this->http()->delete("{$this->baseUrl}/zones/{$this->domain}/records", $payload);

        if (!$resp->successful()) {
            Log::warning("Hostinger API error al borrar DNS '{$name}': " . $resp->body());
            return false;
        }

        Log::info("Hostinger DNS borrado: {$name}.{$this->domain}");
        return true;
    }

    /**
     * Espera a que el DNS resuelva localmente (propagación).
     * Usa dns_get_record en bucle simple.
     */
    public function esperarPropagacion(string $slug, int $segundos = 60): bool
    {
        $fqdn = "{$slug}.{$this->domain}";
        $expectIp = $this->serverIp;
        $deadline = time() + $segundos;

        while (time() < $deadline) {
            $registros = @dns_get_record($fqdn, DNS_A);
            if (!empty($registros)) {
                foreach ($registros as $r) {
                    if (!$expectIp || ($r['ip'] ?? null) === $expectIp) {
                        return true;
                    }
                }
            }
            sleep(3);
        }
        return false;
    }

    /**
     * Convierte un slug en el "name" relativo a la zona.
     * Hostinger usa "slug" (sin el dominio) para subdominios y "@" para raíz.
     */
    private function normalizarName(string $slug): string
    {
        $slug = trim($slug, '. ');
        // Si el caller mandó "la-hacienda.tecnobyte360.com", quitar el dominio
        if (str_ends_with($slug, '.' . $this->domain)) {
            $slug = substr($slug, 0, -1 - strlen($this->domain));
        }
        return $slug === '' ? '@' : $slug;
    }

    private function http()
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
