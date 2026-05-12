<?php

namespace App\Services\Meta;

use App\Models\MetaWhatsappConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 📱 Cliente HTTP de Meta WhatsApp Cloud API (graph.facebook.com).
 *
 * Multi-tenant: lee credenciales de MetaWhatsappConfig por tenant.
 * Si no hay config activa, devuelve false sin error (deja que el caller
 * fallback al provider anterior).
 *
 *   $ok = app(MetaWhatsappCloudService::class)
 *       ->enviarTexto('573216499744', 'Hola Stiven', $tenantId);
 */
class MetaWhatsappCloudService
{
    /**
     * Envía mensaje de texto libre. Requiere sesión abierta (cliente
     * escribió en últimas 24h) según política de Meta.
     */
    public function enviarTexto(string $telefono, string $mensaje, ?int $tenantId = null): bool
    {
        $config = $this->resolverConfig($tenantId);
        if (!$config) return false;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizar($telefono),
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $mensaje],
        ];

        return $this->ejecutar($config, $payload, 'texto');
    }

    /**
     * Envía plantilla aprobada (template). NO requiere sesión abierta.
     * @param string $plantilla nombre EXACTO de la template en Meta
     * @param array  $variables substitución posicional: ['{{1}}' => 'x', '{{2}}' => 'y']
     * @param string $idioma    código BCP-47 (ej 'es_CO', 'es', 'en_US')
     */
    public function enviarPlantilla(
        string $telefono,
        string $plantilla,
        array  $variables = [],
        ?int   $tenantId = null,
        string $idioma   = 'es'
    ): bool {
        $config = $this->resolverConfig($tenantId);
        if (!$config) return false;

        $components = [];
        if (!empty($variables)) {
            $params = [];
            foreach ($variables as $valor) {
                $params[] = ['type' => 'text', 'text' => (string) $valor];
            }
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizar($telefono),
            'type'              => 'template',
            'template'          => [
                'name'       => $plantilla,
                'language'   => ['code' => $idioma ?: ($config->default_lang ?? 'es')],
                'components' => $components,
            ],
        ];

        return $this->ejecutar($config, $payload, "plantilla:{$plantilla}");
    }

    /**
     * Envía imagen por URL pública.
     */
    public function enviarImagen(string $telefono, string $url, ?string $caption = null, ?int $tenantId = null): bool
    {
        $config = $this->resolverConfig($tenantId);
        if (!$config) return false;

        $imagen = ['link' => $url];
        if ($caption) $imagen['caption'] = $caption;

        return $this->ejecutar($config, [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizar($telefono),
            'type'              => 'image',
            'image'             => $imagen,
        ], 'imagen');
    }

    /**
     * Resuelve config Meta para el tenant. Si pasamos tenant_id explícito
     * lo usa, si no, usa el del scope actual.
     */
    public function resolverConfig(?int $tenantId = null): ?MetaWhatsappConfig
    {
        if ($tenantId) {
            return MetaWhatsappConfig::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('activo', true)
                ->first();
        }
        return MetaWhatsappConfig::activaActual();
    }

    private function ejecutar(MetaWhatsappConfig $config, array $payload, string $tipo): bool
    {
        try {
            $resp = Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(20)
                ->post($config->endpointMessages(), $payload);

            if ($resp->successful()) {
                $messageId = $resp->json('messages.0.id');
                Log::info('📤 Meta WA enviado', [
                    'tenant_id' => $config->tenant_id,
                    'tipo'      => $tipo,
                    'to'        => $payload['to'] ?? null,
                    'wa_id'     => $messageId,
                ]);
                return true;
            }

            Log::warning('⚠️ Meta WA fallo HTTP', [
                'tenant_id' => $config->tenant_id,
                'tipo'      => $tipo,
                'status'    => $resp->status(),
                'body'      => mb_substr($resp->body(), 0, 500),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('❌ Meta WA exception', [
                'tenant_id' => $config->tenant_id,
                'tipo'      => $tipo,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Meta acepta E.164 sin '+'. Quitamos caracteres no numéricos.
     */
    private function normalizar(string $telefono): string
    {
        $solo = preg_replace('/\D+/', '', $telefono);
        return $solo ?: $telefono;
    }
}
