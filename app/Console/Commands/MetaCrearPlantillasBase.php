<?php

namespace App\Console\Commands;

use App\Models\MetaWhatsappConfig;
use App\Models\MetaWhatsappPlantilla;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Crea las plantillas base de Alimentos La Hacienda (y reutilizables para
 * cualquier restaurante/comercio) directamente en Meta vía Graph API.
 *
 * Uso:
 *   php artisan meta:crear-plantillas-base {tenant_id}
 *   php artisan meta:crear-plantillas-base 1
 *
 * Después de ejecutar, Meta las pone en estado PENDING (revisión 24-48h).
 * Una vez aprobadas, las puedes usar en /campanas y en disparadores.
 */
class MetaCrearPlantillasBase extends Command
{
    protected $signature = 'meta:crear-plantillas-base {tenant_id}';
    protected $description = 'Crea las plantillas base (pedido_*, bienvenida, etc) en Meta vía API';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');

        $cfg = MetaWhatsappConfig::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('activo', true)
            ->first();

        if (!$cfg) {
            $this->error("No hay MetaWhatsappConfig activa para tenant_id={$tenantId}");
            return 1;
        }

        if (empty($cfg->waba_id) || empty($cfg->access_token)) {
            $this->error("Falta waba_id o access_token en config");
            return 1;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/message_templates',
            $cfg->api_version ?: 'v25.0',
            $cfg->waba_id
        );

        $plantillas = $this->plantillas();
        $this->info("Creando " . count($plantillas) . " plantillas en Meta...");

        $resumen = ['ok' => 0, 'duplicada' => 0, 'fallo' => 0];

        foreach ($plantillas as $p) {
            $this->line("→ {$p['name']} ({$p['category']})...");

            try {
                $resp = Http::withToken($cfg->access_token)
                    ->acceptJson()
                    ->timeout(20)
                    ->post($url, $p);

                $body = $resp->json();

                if ($resp->successful() && isset($body['id'])) {
                    // Persistir local también
                    MetaWhatsappPlantilla::updateOrCreate(
                        ['tenant_id' => $tenantId, 'nombre' => $p['name'], 'idioma' => $p['language']],
                        [
                            'categoria'    => $p['category'],
                            'estado'       => 'PENDING',
                            'body_preview' => $this->extraerBody($p['components']),
                            'footer'       => $this->extraerFooter($p['components']),
                            'num_variables' => $this->contarVars($p['components']),
                            'activa'       => true,
                        ]
                    );
                    $this->info("   ✓ creada (id={$body['id']})");
                    $resumen['ok']++;
                } elseif (isset($body['error']['code']) && $body['error']['code'] == 100 && str_contains($body['error']['message'] ?? '', 'already exists')) {
                    $this->warn("   ⚠ ya existe");
                    $resumen['duplicada']++;
                } else {
                    $msg = $body['error']['message'] ?? $resp->body();
                    $this->error("   ✗ fallo: " . mb_substr($msg, 0, 200));
                    $resumen['fallo']++;
                }
            } catch (\Throwable $e) {
                $this->error("   ✗ excepción: " . $e->getMessage());
                $resumen['fallo']++;
            }
        }

        $this->newLine();
        $this->info("RESUMEN: ✓ {$resumen['ok']} creadas | ⚠ {$resumen['duplicada']} ya existían | ✗ {$resumen['fallo']} fallaron");
        $this->info("Revisa en business.facebook.com/wa/manage/message-templates el estado de aprobación.");

        return 0;
    }

    /**
     * Plantillas base del sistema. Variables {{N}} son posicionales.
     */
    private function plantillas(): array
    {
        return [
            // ─── UTILITY (operacionales) ─────────────────────────────
            [
                'name' => 'pedido_confirmado',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}}, tu pedido *#{{2}}* fue confirmado por un total de \${{3}}.\n\nPronto te avisamos cuando salga para tu casa. ¡Gracias por tu compra!",
                        'example' => ['body_text' => [['Stiven', '18', '94.650']]],
                    ],
                ],
            ],
            [
                'name' => 'pedido_en_proceso',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}}, ya estamos preparando tu pedido *#{{2}}* 👨‍🍳\n\nTe avisamos apenas salga para entrega.",
                        'example' => ['body_text' => [['Stiven', '18']]],
                    ],
                ],
            ],
            [
                'name' => 'pedido_en_camino',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "{{1}}, tu pedido *#{{2}}* ya va en camino 🛵\n\nDomiciliario: {{3}}\nTiempo estimado: {{4}} minutos",
                        'example' => ['body_text' => [['Stiven', '18', 'Juan Pérez', '20']]],
                    ],
                ],
            ],
            [
                'name' => 'pedido_entregado',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Listo {{1}} ✅\n\nTu pedido *#{{2}}* fue entregado. ¡Gracias por confiar en nosotros!\n\nEn un momento te enviamos una breve encuesta.",
                        'example' => ['body_text' => [['Stiven', '18']]],
                    ],
                ],
            ],
            [
                'name' => 'pedido_cancelado',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}}, tu pedido *#{{2}}* fue cancelado.\n\nMotivo: {{3}}\n\nSi necesitas ayuda, escríbenos.",
                        'example' => ['body_text' => [['Stiven', '18', 'Sin stock']]],
                    ],
                ],
            ],
            [
                'name' => 'encuesta_post_entrega',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "{{1}}, ¿cómo te pareció tu pedido *#{{2}}*? 🌟\n\nCalifícanos del 1 al 5 respondiendo a este mensaje. Tu opinión nos ayuda a mejorar.",
                        'example' => ['body_text' => [['Stiven', '18']]],
                    ],
                ],
            ],
            [
                'name' => 'bienvenida_cliente',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}}, bienvenido a {{2}} 🍽️\n\nEstamos listos para tu pedido. Escríbenos cuando quieras y con gusto te atendemos.",
                        'example' => ['body_text' => [['Stiven', 'Alimentos La Hacienda']]],
                    ],
                ],
            ],
            [
                'name' => 'recordatorio_pago',
                'category' => 'UTILITY',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}}, tu pedido *#{{2}}* tiene un pago pendiente por \${{3}}.\n\n¿Puedes confirmar el pago para procesar tu pedido? Si necesitas ayuda, escríbenos.",
                        'example' => ['body_text' => [['Stiven', '18', '94.650']]],
                    ],
                ],
            ],

            // ─── MARKETING (promocionales) ────────────────────────────
            [
                'name' => 'promocion_general',
                'category' => 'MARKETING',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "Hola {{1}} 🎉\n\nTenemos *{{2}} de descuento* en {{3}} válido hasta el {{4}}.\n\nResponde *SI* para hacer tu pedido. ¡No te lo pierdas!",
                        'example' => ['body_text' => [['Stiven', '20%', 'chuletas', '30 de mayo']]],
                    ],
                ],
            ],
            [
                'name' => 'felicitacion_cumpleanos',
                'category' => 'MARKETING',
                'language' => 'es',
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "🎂 ¡Feliz cumpleaños {{1}}!\n\nHoy queremos regalarte *{{2}}* en tu próximo pedido.\n\nVálido hasta {{3}}. ¡Que tengas un día increíble!",
                        'example' => ['body_text' => [['Stiven', '20% de descuento', '31 de mayo']]],
                    ],
                ],
            ],
        ];
    }

    private function extraerBody(array $components): string
    {
        foreach ($components as $c) {
            if (($c['type'] ?? '') === 'BODY') return $c['text'] ?? '';
        }
        return '';
    }

    private function extraerFooter(array $components): ?string
    {
        foreach ($components as $c) {
            if (($c['type'] ?? '') === 'FOOTER') return $c['text'] ?? null;
        }
        return null;
    }

    private function contarVars(array $components): int
    {
        $body = $this->extraerBody($components);
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
        return count(array_unique($m[1] ?? []));
    }
}
