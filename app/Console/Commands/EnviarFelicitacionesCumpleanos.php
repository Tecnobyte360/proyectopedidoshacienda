<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\ConfiguracionBot;
use App\Services\WhatsappSenderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnviarFelicitacionesCumpleanos extends Command
{
    protected $signature = 'clientes:felicitar-cumpleanos
                            {--dry-run : Solo muestra a quiénes se les mandaría el mensaje, sin enviar}
                            {--force   : Reenvía aunque ya se haya felicitado este año}';

    protected $description = 'Envía mensaje de feliz cumpleaños a los clientes cuyo cumpleaños es hoy.';

    public function handle(WhatsappSenderService $wa): int
    {
        $config = ConfiguracionBot::actual();

        if (!$config->cumpleanos_activo && !$this->option('dry-run')) {
            $this->warn('🚫 Felicitación de cumpleaños DESACTIVADA en la configuración del bot. Usa --dry-run para probar igual.');
            return Command::SUCCESS;
        }

        $hoy = Carbon::now();
        $anoActual = (int) $hoy->format('Y');
        $mes = (int) $hoy->format('m');
        $dia = (int) $hoy->format('d');

        // Clientes cuyo cumpleaños es hoy (mes y día coinciden)
        $query = Cliente::query()
            ->whereNotNull('fecha_nacimiento')
            ->where('activo', true)
            ->whereNotNull('telefono_normalizado')
            ->whereRaw('MONTH(fecha_nacimiento) = ?', [$mes])
            ->whereRaw('DAY(fecha_nacimiento) = ?',   [$dia]);

        if (!$this->option('force')) {
            $query->where(function ($q) use ($anoActual) {
                $q->whereNull('ultima_felicitacion_anio')
                  ->orWhere('ultima_felicitacion_anio', '!=', $anoActual);
            });
        }

        $clientes = $query->get();

        if ($clientes->isEmpty()) {
            $this->info('📅 No hay cumpleaños hoy (o ya fueron felicitados).');
            return Command::SUCCESS;
        }

        $this->info("🎂 {$clientes->count()} cumpleañer@(s) hoy.");

        $plantilla = $this->obtenerPlantilla($config);
        $enviados = 0;
        $fallidos = 0;

        foreach ($clientes as $cliente) {
            $nombre = trim($cliente->nombre ?: 'crack') ?: 'crack';
            $mensaje = $this->renderizar($plantilla, $cliente);

            $linea = sprintf('  → %s (%s)', $nombre, $cliente->telefono_normalizado);

            if ($this->option('dry-run')) {
                $this->line($linea . ' [DRY-RUN, no se envía]');
                continue;
            }

            $ok = $wa->enviarTexto($cliente->telefono_normalizado, $mensaje);

            if ($ok) {
                $cliente->update(['ultima_felicitacion_anio' => $anoActual]);
                $this->line($linea . ' ✅');
                $enviados++;
                Log::info('🎂 Felicitación enviada', [
                    'cliente_id' => $cliente->id,
                    'nombre'     => $nombre,
                    'telefono'   => $cliente->telefono_normalizado,
                ]);
            } else {
                $this->line($linea . ' ❌');
                $fallidos++;
            }

            // Pequeña pausa para no saturar la API de WhatsApp
            usleep(500_000);
        }

        $this->newLine();
        $this->info("📨 Enviados: {$enviados}");
        if ($fallidos > 0) $this->error("⚠️ Fallidos: {$fallidos}");

        return Command::SUCCESS;
    }

    private function obtenerPlantilla(ConfiguracionBot $config): string
    {
        $plantilla = trim((string) $config->cumpleanos_mensaje);
        return $plantilla !== ''
            ? $plantilla
            : ConfiguracionBot::CUMPLEANOS_PLANTILLA_DEFAULT;
    }

    private function renderizar(string $plantilla, Cliente $cliente): string
    {
        $nombre = trim((string) $cliente->nombre);
        $primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : '';

        return strtr($plantilla, [
            '{nombre}'        => $primerNombre ?: 'crack',
            '{nombre_completo}' => $nombre ?: 'crack',
        ]);
    }
}
