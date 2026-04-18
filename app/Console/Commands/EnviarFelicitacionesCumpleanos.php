<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\ConfiguracionBot;
use App\Models\FelicitacionCumpleanos;
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

        $hoy = Carbon::now('America/Bogota');
        $anoActual = (int) $hoy->format('Y');

        // ── Validar día de la semana permitido ───────────────────────────
        // Carbon: dayOfWeek es 0=Dom...6=Sab. Convertimos a índice L-D (0=Lun..6=Dom).
        $diasSemanaStr = str_pad((string) ($config->cumpleanos_dias_semana ?: '1111111'), 7, '1');
        $diasSemana = [
            1 => 0, // Lun
            2 => 1, // Mar
            3 => 2, // Mié
            4 => 3, // Jue
            5 => 4, // Vie
            6 => 5, // Sáb
            0 => 6, // Dom
        ];
        $idxHoy = $diasSemana[$hoy->dayOfWeek] ?? 0;
        if (($diasSemanaStr[$idxHoy] ?? '1') !== '1' && !$this->option('force')) {
            $this->warn('🚫 Hoy (' . $hoy->isoFormat('dddd') . ') NO está en los días permitidos según la configuración.');
            return Command::SUCCESS;
        }

        // ── Validar ventana horaria ──────────────────────────────────────
        $horaActual = $hoy->format('H:i');
        $desde = $config->cumpleanos_ventana_desde ?: '00:00';
        $hasta = $config->cumpleanos_ventana_hasta ?: '23:59';
        if (!$this->option('force') && ($horaActual < $desde || $horaActual > $hasta)) {
            $this->warn("🚫 Hora actual {$horaActual} está fuera de la ventana permitida ({$desde} - {$hasta}).");
            return Command::SUCCESS;
        }

        // ── Aplicar anticipación: calcular fecha objetivo ────────────────
        $anticipacion = (int) ($config->cumpleanos_dias_anticipacion ?? 0);
        $fechaObjetivo = $hoy->copy()->addDays($anticipacion);
        $mes = (int) $fechaObjetivo->format('m');
        $dia = (int) $fechaObjetivo->format('d');

        if ($anticipacion > 0) {
            $this->info("📅 Buscando cumpleañeros con {$anticipacion} día(s) de anticipación → fecha objetivo: {$fechaObjetivo->format('d/m')}");
        }

        // Clientes cuyo cumpleaños cae en la fecha objetivo (compat MySQL/SQLite)
        $driver = \DB::connection()->getDriverName();
        $mesExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', fecha_nacimiento) AS INTEGER)"
            : "MONTH(fecha_nacimiento)";
        $diaExpr = $driver === 'sqlite'
            ? "CAST(strftime('%d', fecha_nacimiento) AS INTEGER)"
            : "DAY(fecha_nacimiento)";

        $query = Cliente::query()
            ->whereNotNull('fecha_nacimiento')
            ->where('activo', true)
            ->whereNotNull('telefono_normalizado')
            ->whereRaw("{$mesExpr} = ?", [$mes])
            ->whereRaw("{$diaExpr} = ?", [$dia]);

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

        $origen = $this->option('force')
            ? FelicitacionCumpleanos::ORIGEN_FORCE
            : ($this->option('dry-run')
                ? FelicitacionCumpleanos::ORIGEN_MANUAL
                : FelicitacionCumpleanos::ORIGEN_SCHEDULED);

        foreach ($clientes as $cliente) {
            $nombre = trim($cliente->nombre ?: 'crack') ?: 'crack';
            $mensaje = $this->renderizar($plantilla, $cliente);

            // Resolver por cuál WhatsApp sale el mensaje
            $connectionId = $cliente->conexionWhatsappPreferida();

            $linea = sprintf(
                '  → %s (%s)%s',
                $nombre,
                $cliente->telefono_normalizado,
                $connectionId ? " [conn #{$connectionId}]" : ''
            );

            // Registro base (todos los intentos quedan en historial)
            $registro = FelicitacionCumpleanos::create([
                'cliente_id'     => $cliente->id,
                'cliente_nombre' => $nombre,
                'telefono'       => $cliente->telefono_normalizado,
                'connection_id'  => $connectionId,
                'mensaje'        => $mensaje,
                'origen'         => $origen,
                'anio'           => $anoActual,
                'estado'         => FelicitacionCumpleanos::ESTADO_DRY_RUN, // placeholder
                'enviado_at'     => now(),
            ]);

            if ($this->option('dry-run')) {
                $registro->update(['estado' => FelicitacionCumpleanos::ESTADO_DRY_RUN]);
                $this->line($linea . ' [DRY-RUN, no se envía]');
                continue;
            }

            // ── Envío con reintentos según configuración ─────────────
            $maxReintentos = (int) ($config->cumpleanos_reintentos_max ?? 2);
            $ok = false;
            $ultimoError = null;
            $intentos = 0;

            for ($i = 0; $i <= $maxReintentos; $i++) {
                $intentos++;
                try {
                    $ok = $wa->enviarTexto($cliente->telefono_normalizado, $mensaje, $connectionId);
                    if ($ok) break;
                    $ultimoError = 'La API de WhatsApp respondió con error (intento ' . $intentos . ').';
                } catch (\Throwable $e) {
                    $ultimoError = $e->getMessage();
                }

                if (!$ok && $i < $maxReintentos) {
                    // Esperar un poco antes del siguiente intento (backoff simple)
                    sleep(min(3, $i + 1));
                }
            }

            if (!$ok) {
                $registro->update([
                    'estado'        => FelicitacionCumpleanos::ESTADO_FALLIDO,
                    'error_detalle' => $ultimoError . ' [Intentos: ' . $intentos . ']',
                ]);
                $this->line($linea . ' ❌ (' . $intentos . ' intento(s))');
                $fallidos++;
                continue;
            }

            // Éxito (llegamos aquí solo si ok=true)
            $cliente->update(['ultima_felicitacion_anio' => $anoActual]);
            $registro->update(['estado' => FelicitacionCumpleanos::ESTADO_ENVIADO]);

            // 🎁 Otorgar beneficio de envío gratis por cumpleaños
            $diasVigencia = max(1, (int) ($config->cumpleanos_dias_vigencia_beneficio ?? 3));
            try {
                $beneficio = \App\Models\BeneficioCliente::create([
                    'cliente_id'      => $cliente->id,
                    'felicitacion_id' => $registro->id,
                    'tipo'            => \App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS,
                    'origen'          => \App\Models\BeneficioCliente::ORIGEN_CUMPLEANOS,
                    'descripcion'     => "Regalo de cumpleaños {$anoActual} — vigente {$diasVigencia} día(s)",
                    'otorgado_at'     => now(),
                    'vigente_hasta'   => now()->addDays($diasVigencia - 1)->toDateString(),
                ]);
                Log::info('🎁 Beneficio envío gratis otorgado', [
                    'cliente_id'    => $cliente->id,
                    'beneficio_id'  => $beneficio->id,
                    'vigente_hasta' => $beneficio->vigente_hasta,
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear beneficio de cumpleaños: ' . $e->getMessage());
            }

            $this->line($linea . ' ✅ (envío gratis ' . $diasVigencia . 'd)');
            $enviados++;

            Log::info('🎂 Felicitación enviada', [
                'cliente_id'     => $cliente->id,
                'nombre'         => $nombre,
                'telefono'       => $cliente->telefono_normalizado,
                'intentos'       => $intentos,
                'felicitacion_id' => $registro->id,
            ]);

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
