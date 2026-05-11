<?php

namespace App\Console\Commands;

use App\Models\BotAlerta;
use App\Models\ConversacionWhatsapp;
use App\Models\Pedido;
use App\Models\Tenant;
use App\Services\Bots\AlertasService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🔍 AUDITORÍA DIARIA DEL BOT
 *
 * Job nocturno (corre a las 23:55 cada día) que valida la integridad
 * de los datos generados por el bot. Si encuentra problemas, dispara
 * alertas al admin.
 *
 * Validaciones:
 *   1. TODOS los pedidos del día tienen cédula válida.
 *   2. Ningún cliente local con cédula vacía/nula.
 *   3. Ninguna conversación con >12 mensajes sin handoff ni pedido.
 *   4. Pedidos creados sin exportar a SGI.
 *   5. Conversaciones marcadas para humano sin atender en >2h.
 */
class AuditoriaDiariaBot extends Command
{
    protected $signature = 'bot:auditoria-diaria
                            {--tenant= : ID del tenant (opcional, si omites audita todos)}
                            {--dry-run : Solo muestra el reporte, no envía alertas}';

    protected $description = 'Audita la integridad de los datos del bot (pedidos, clientes, conversaciones)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun   = (bool) $this->option('dry-run');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('activo', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants activos para auditar.');
            return self::SUCCESS;
        }

        $this->info('═══════════════════════════════════════════════');
        $this->info('🔍 AUDITORÍA DIARIA DEL BOT — ' . now()->format('Y-m-d H:i'));
        $this->info('═══════════════════════════════════════════════');

        foreach ($tenants as $tenant) {
            app(TenantManager::class)->set($tenant);
            $this->auditarTenant($tenant, $dryRun);
        }

        return self::SUCCESS;
    }

    private function auditarTenant(Tenant $tenant, bool $dryRun): void
    {
        $this->newLine();
        $this->info("📊 TENANT: {$tenant->nombre} (id={$tenant->id})");
        $this->line(str_repeat('─', 50));

        $problemas = [];

        // 1. Pedidos sin cédula
        $sinCedula = Pedido::where('tenant_id', $tenant->id)
            ->whereDate('created_at', today())
            ->where(function ($q) {
                $q->whereNull('cliente_cedula')->orWhere('cliente_cedula', '');
            })
            ->count();

        if ($sinCedula > 0) {
            $problemas[] = "🚨 {$sinCedula} pedido(s) HOY sin cédula registrada";
        }

        // 2. Clientes locales con cédula vacía pero con pedidos
        $clientesSinCedula = DB::table('clientes')
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->whereNull('cedula')->orWhere('cedula', '');
            })
            ->where('total_pedidos', '>', 0)
            ->count();

        if ($clientesSinCedula > 0) {
            $problemas[] = "⚠️ {$clientesSinCedula} cliente(s) con pedidos pero sin cédula registrada";
        }

        // 3. Conversaciones con >12 mensajes user sin pedido ni handoff
        $convsLargasSinResolver = ConversacionWhatsapp::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subDays(2))
            ->whereNull('pedido_id')
            ->where('requiere_humano', false)
            ->where('total_mensajes_cliente', '>', 12)
            ->count();

        if ($convsLargasSinResolver > 0) {
            $problemas[] = "💬 {$convsLargasSinResolver} conversación(es) con >12 mensajes user sin cerrar (posible bot atascado)";
        }

        // 4. Pedidos creados sin exportar a SGI
        $sinExportar = Pedido::where('tenant_id', $tenant->id)
            ->whereDate('created_at', today())
            ->leftJoin('integracion_export_logs', 'pedidos.id', '=', 'integracion_export_logs.pedido_id')
            ->whereNull('integracion_export_logs.id')
            ->count();

        if ($sinExportar > 0) {
            $problemas[] = "📦 {$sinExportar} pedido(s) sin exportación a SGI registrada";
        }

        // 5. Conversaciones marcadas para humano sin atender (>2h)
        $humanoPendientes = ConversacionWhatsapp::where('tenant_id', $tenant->id)
            ->where('requiere_humano', true)
            ->whereNull('humano_atendido_at')
            ->where('humano_solicitado_at', '<=', now()->subHours(2))
            ->count();

        if ($humanoPendientes > 0) {
            $problemas[] = "🤝 {$humanoPendientes} conversación(es) marcadas para humano hace >2h sin atender";
        }

        // ── Reporte ──
        $kpis = [
            'Pedidos hoy'          => Pedido::where('tenant_id', $tenant->id)->whereDate('created_at', today())->count(),
            'Total facturado hoy'  => '$' . number_format(
                Pedido::where('tenant_id', $tenant->id)->whereDate('created_at', today())->sum('total'),
                0, ',', '.'
            ),
            'Conversaciones hoy'   => ConversacionWhatsapp::where('tenant_id', $tenant->id)->whereDate('created_at', today())->count(),
            'Alertas hoy'          => BotAlerta::where('tenant_id', $tenant->id)->whereDate('created_at', today())->count(),
        ];

        foreach ($kpis as $k => $v) $this->line("  {$k}: {$v}");

        if (empty($problemas)) {
            $this->newLine();
            $this->info('  ✅ TODO EN ORDEN — sin problemas detectados');
            return;
        }

        $this->newLine();
        $this->warn('  ⚠️ PROBLEMAS DETECTADOS:');
        foreach ($problemas as $p) {
            $this->line("    {$p}");
        }

        // Disparar alerta
        if (!$dryRun) {
            try {
                app(AlertasService::class)->notificar(
                    'auditoria_diaria',
                    "🔍 Auditoría diaria — {$tenant->nombre}",
                    "Problemas detectados:\n\n" . implode("\n", $problemas) .
                    "\n\nKPIs:\n" . collect($kpis)->map(fn ($v, $k) => "  • {$k}: {$v}")->implode("\n") .
                    "\n\nRevisa /bot-monitor para más detalles.",
                    [
                        'tenant_id' => $tenant->id,
                        'problemas' => $problemas,
                        'severidad' => count($problemas) >= 3 ? 'critica' : 'alta',
                    ]
                );
                $this->line('  📧 Alerta enviada al admin');
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar alerta de auditoría: ' . $e->getMessage());
            }
        }
    }
}
