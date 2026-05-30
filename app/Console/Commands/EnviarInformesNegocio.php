<?php

namespace App\Console\Commands;

use App\Mail\InformeNegocioMail;
use App\Models\TenantInformeConfig;
use App\Services\InformeAnalistaService;
use App\Services\InformeNegocioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarInformesNegocio extends Command
{
    protected $signature = 'informes:enviar {--tenant= : Forzar envío para un tenant_id específico (ignora frecuencia y horario)}';

    protected $description = 'Envía los informes periódicos a los admins de los tenants que estén configurados.';

    public function handle(InformeNegocioService $svc, InformeAnalistaService $analista): int
    {
        $ahora = now();
        $tenantForzado = $this->option('tenant');

        $configs = TenantInformeConfig::query()->with('tenant');
        if ($tenantForzado) {
            $configs->where('tenant_id', $tenantForzado);
        } else {
            $configs->where('activo', true);
        }
        $configs = $configs->get();

        $enviados = 0;
        foreach ($configs as $cfg) {
            if (!$cfg->tenant) {
                $this->warn("Tenant {$cfg->tenant_id} no existe — skip");
                continue;
            }

            // Si es forzado, ignorar horarios. Si no, validar.
            if (!$tenantForzado && !$cfg->tocaEnviar($ahora)) {
                continue;
            }

            // Generar informe
            $data = $svc->generar($cfg->tenant, $cfg->frecuencia);

            // 🧠 Análisis IA con insights y recomendaciones (Claude Haiku)
            try {
                $data['analisis'] = $analista->analizar($cfg->tenant->nombre, $data);
            } catch (\Throwable $e) {
                Log::warning('Análisis IA falló (continúa con métricas crudas)', ['error' => $e->getMessage()]);
                $data['analisis'] = ['titular' => '', 'resumen' => '', 'insights' => [], 'recomendaciones' => []];
            }

            // Métricas a incluir
            $incluir = [
                'volumen'         => $cfg->inc_volumen,
                'horas_pico'      => $cfg->inc_horas_pico,
                'tiempo_respuesta'=> $cfg->inc_tiempo_respuesta,
                'reacciones'      => $cfg->inc_reacciones,
                'top_clientes'    => $cfg->inc_top_clientes,
                'sin_responder'   => $cfg->inc_sin_responder,
                'palabras_top'    => $cfg->inc_palabras_top,
            ];

            $emails = collect($cfg->emails ?? [])->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values();
            if ($emails->isEmpty()) {
                $this->warn("Tenant {$cfg->tenant->nombre}: sin emails configurados — skip");
                continue;
            }

            try {
                foreach ($emails as $email) {
                    Mail::to($email)->send(new InformeNegocioMail($cfg->tenant, $data, $incluir));
                }
                $cfg->update(['ultimo_envio_at' => $ahora]);
                $this->info("✅ Informe {$cfg->frecuencia} enviado a {$emails->count()} destinatarios de {$cfg->tenant->nombre}");
                $enviados++;
            } catch (\Throwable $e) {
                Log::error('Error enviando informe negocio', [
                    'tenant_id' => $cfg->tenant_id,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("❌ {$cfg->tenant->nombre}: " . $e->getMessage());
            }
        }

        $this->info(PHP_EOL . "Total informes enviados: $enviados");
        return self::SUCCESS;
    }
}
