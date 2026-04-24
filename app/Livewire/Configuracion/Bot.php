<?php

namespace App\Livewire\Configuracion;

use App\Models\ConfiguracionBot;
use App\Services\BotPromptService;
use Livewire\Component;

class Bot extends Component
{
    public bool   $enviar_imagenes_productos = false;
    public bool   $transcribir_audios        = true;
    public int    $max_imagenes_por_mensaje  = 3;
    public bool   $enviar_imagen_destacados  = false;
    public bool   $saludar_con_promociones   = true;

    // Derivación automática por IA
    public bool   $derivacion_activa                    = true;
    public bool   $derivacion_fallback_activa           = true;
    public string $derivacion_instrucciones_ia          = '';
    public string $derivacion_frases_deteccion          = '';
    public ?int   $derivacion_departamento_fallback_id  = null;

    // Buffer de mensajes (debounce)
    public bool   $agrupar_mensajes_activo   = true;
    public int    $agrupar_mensajes_segundos = 5;

    public string $modelo_openai             = 'gpt-4o-mini';
    public float  $temperatura               = 0.85;
    public int    $max_tokens                = 700;

    public string $nombre_asesora            = 'Sofía';
    public string $frase_bienvenida          = '';
    public string $info_empresa              = '';
    public bool   $activo                    = true;

    // Prompt personalizado
    public bool   $usar_prompt_personalizado = false;
    public string $system_prompt             = '';

    // Instrucciones extra (se SUMAN al prompt, no reemplazan)
    public string $instrucciones_extra       = '';

    // Felicitaciones de cumpleaños
    public bool   $cumpleanos_activo             = true;
    public string $cumpleanos_hora               = '09:00';
    public string $cumpleanos_mensaje            = '';
    public int    $cumpleanos_dias_anticipacion  = 0;     // 0=mismo día
    public int    $cumpleanos_reintentos_max     = 2;
    public string $cumpleanos_ventana_desde      = '08:00';
    public string $cumpleanos_ventana_hasta      = '20:00';
    // Array de 7 booleans: L M X J V S D
    public array  $cumpleanos_dias_semana_arr    = [true, true, true, true, true, true, true];

    // Conexión de WhatsApp por defecto para envíos salientes (cumpleaños, etc)
    public ?int   $connection_id_default                  = null;

    // Días que dura el beneficio de envío gratis después de la felicitación
    public int    $cumpleanos_dias_vigencia_beneficio     = 3;

    public array $modelosDisponibles = [
        'gpt-4o-mini' => 'GPT-4o mini (rápido, económico)',
        'gpt-4o'      => 'GPT-4o (más natural, más caro)',
        'gpt-4-turbo' => 'GPT-4 Turbo (potente)',
    ];

    public function mount(): void
    {
        $cfg = ConfiguracionBot::actual();

        $this->enviar_imagenes_productos = (bool) $cfg->enviar_imagenes_productos;
        $this->transcribir_audios        = (bool) ($cfg->transcribir_audios ?? true);
        $this->max_imagenes_por_mensaje  = (int) $cfg->max_imagenes_por_mensaje;
        $this->enviar_imagen_destacados  = (bool) $cfg->enviar_imagen_destacados;
        $this->saludar_con_promociones   = (bool) $cfg->saludar_con_promociones;

        $this->derivacion_activa                   = (bool) ($cfg->derivacion_activa ?? true);
        $this->derivacion_fallback_activa          = (bool) ($cfg->derivacion_fallback_activa ?? true);
        $this->derivacion_instrucciones_ia         = (string) ($cfg->derivacion_instrucciones_ia ?: ConfiguracionBot::DERIVACION_INSTRUCCIONES_DEFAULT);
        $this->derivacion_frases_deteccion         = (string) ($cfg->derivacion_frases_deteccion ?: ConfiguracionBot::DERIVACION_FRASES_DEFAULT);
        $this->derivacion_departamento_fallback_id = $cfg->derivacion_departamento_fallback_id;
        $this->agrupar_mensajes_activo   = (bool) ($cfg->agrupar_mensajes_activo ?? true);
        $this->agrupar_mensajes_segundos = (int) ($cfg->agrupar_mensajes_segundos ?? 5);
        $this->modelo_openai             = (string) ($cfg->modelo_openai ?? 'gpt-4o-mini');
        $this->temperatura               = (float) $cfg->temperatura;
        $this->max_tokens                = (int) $cfg->max_tokens;
        $this->nombre_asesora            = (string) ($cfg->nombre_asesora ?? 'Sofía');
        $this->frase_bienvenida          = (string) ($cfg->frase_bienvenida ?? '');
        $this->info_empresa              = (string) ($cfg->info_empresa ?? '');
        $this->activo                    = (bool) $cfg->activo;

        $this->usar_prompt_personalizado = (bool) ($cfg->usar_prompt_personalizado ?? false);
        $this->system_prompt             = (string) ($cfg->system_prompt ?? '');
        $this->instrucciones_extra       = (string) ($cfg->instrucciones_extra ?? '');

        $this->cumpleanos_activo  = (bool) ($cfg->cumpleanos_activo ?? true);
        $this->cumpleanos_hora    = (string) ($cfg->cumpleanos_hora ?: '09:00');
        $this->cumpleanos_mensaje = (string) ($cfg->cumpleanos_mensaje ?: ConfiguracionBot::CUMPLEANOS_PLANTILLA_DEFAULT);

        $this->cumpleanos_dias_anticipacion = (int) ($cfg->cumpleanos_dias_anticipacion ?? 0);
        $this->cumpleanos_reintentos_max    = (int) ($cfg->cumpleanos_reintentos_max ?? 2);
        $this->cumpleanos_ventana_desde     = (string) ($cfg->cumpleanos_ventana_desde ?: '08:00');
        $this->cumpleanos_ventana_hasta     = (string) ($cfg->cumpleanos_ventana_hasta ?: '20:00');

        // String '1111111' → array de booleans [true, true, true, true, true, true, true]
        $diasStr = str_pad((string) ($cfg->cumpleanos_dias_semana ?: '1111111'), 7, '1');
        $this->cumpleanos_dias_semana_arr = [];
        for ($i = 0; $i < 7; $i++) {
            $this->cumpleanos_dias_semana_arr[] = ($diasStr[$i] ?? '1') === '1';
        }

        $this->connection_id_default = $cfg->connection_id_default
            ? (int) $cfg->connection_id_default
            : null;

        $this->cumpleanos_dias_vigencia_beneficio = (int) ($cfg->cumpleanos_dias_vigencia_beneficio ?? 3);
    }

    public function cargarPlantillaCumpleanosDefault(): void
    {
        $this->cumpleanos_mensaje = ConfiguracionBot::CUMPLEANOS_PLANTILLA_DEFAULT;
        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Plantilla de cumpleaños restaurada — recuerda guardar.',
        ]);
    }

    /**
     * Devuelve los clientes cuyo cumpleaños es HOY.
     * Se muestra como tabla en la UI para saber a quién le llegaría el mensaje.
     */
    public function getCumpleanerosHoyProperty()
    {
        $hoy = now('America/Bogota');

        // Compat MySQL / SQLite
        $driver = \DB::connection()->getDriverName();
        $mesExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', fecha_nacimiento) AS INTEGER)"
            : "MONTH(fecha_nacimiento)";
        $diaExpr = $driver === 'sqlite'
            ? "CAST(strftime('%d', fecha_nacimiento) AS INTEGER)"
            : "DAY(fecha_nacimiento)";

        return \App\Models\Cliente::query()
            ->whereNotNull('fecha_nacimiento')
            ->where('activo', true)
            ->whereNotNull('telefono_normalizado')
            ->whereRaw("{$mesExpr} = ?", [(int) $hoy->format('m')])
            ->whereRaw("{$diaExpr} = ?", [(int) $hoy->format('d')])
            ->get(['id', 'nombre', 'telefono_normalizado', 'fecha_nacimiento', 'ultima_felicitacion_anio']);
    }

    /**
     * Envía YA la felicitación a un cliente específico (botón "Enviar ahora").
     */
    public function enviarFelicitacionManual(int $clienteId): void
    {
        $cliente = \App\Models\Cliente::find($clienteId);
        if (!$cliente || !$cliente->telefono_normalizado) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Cliente no encontrado.']);
            return;
        }

        $plantilla = trim($this->cumpleanos_mensaje) !== ''
            ? $this->cumpleanos_mensaje
            : ConfiguracionBot::CUMPLEANOS_PLANTILLA_DEFAULT;

        $nombre = trim($cliente->nombre ?: 'crack');
        $primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : 'crack';
        $mensaje = strtr($plantilla, [
            '{nombre}'          => $primerNombre,
            '{nombre_completo}' => $nombre,
        ]);

        $anioActual = (int) now()->format('Y');
        $connectionId = $cliente->conexionWhatsappPreferida();

        $registro = \App\Models\FelicitacionCumpleanos::create([
            'cliente_id'     => $cliente->id,
            'cliente_nombre' => $nombre,
            'telefono'       => $cliente->telefono_normalizado,
            'connection_id'  => $connectionId,
            'mensaje'        => $mensaje,
            'origen'         => \App\Models\FelicitacionCumpleanos::ORIGEN_MANUAL,
            'anio'           => $anioActual,
            'estado'         => \App\Models\FelicitacionCumpleanos::ESTADO_DRY_RUN,
            'enviado_at'     => now(),
        ]);

        try {
            $wa = app(\App\Services\WhatsappSenderService::class);
            $ok = $wa->enviarTexto($cliente->telefono_normalizado, $mensaje, $connectionId);

            if ($ok) {
                $cliente->update(['ultima_felicitacion_anio' => $anioActual]);
                $registro->update(['estado' => \App\Models\FelicitacionCumpleanos::ESTADO_ENVIADO]);

                // 🎁 Crear beneficio de envío gratis
                try {
                    $cfg = ConfiguracionBot::actual();
                    $diasVigencia = max(1, (int) ($cfg->cumpleanos_dias_vigencia_beneficio ?? 3));
                    \App\Models\BeneficioCliente::create([
                        'cliente_id'      => $cliente->id,
                        'felicitacion_id' => $registro->id,
                        'tipo'            => \App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS,
                        'origen'          => \App\Models\BeneficioCliente::ORIGEN_CUMPLEANOS,
                        'descripcion'     => "Regalo de cumpleaños {$anioActual} — envío manual",
                        'otorgado_at'     => now(),
                        'vigente_hasta'   => now()->addDays($diasVigencia - 1)->toDateString(),
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Beneficio manual cumpleaños falló: ' . $e->getMessage());
                }

                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => '✅ Felicitación enviada a ' . $nombre . ' (envío gratis otorgado)',
                ]);
            } else {
                $registro->update([
                    'estado'        => \App\Models\FelicitacionCumpleanos::ESTADO_FALLIDO,
                    'error_detalle' => 'La API de WhatsApp respondió con error.',
                ]);

                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => '❌ No se pudo enviar a ' . $nombre . '. Revisa el historial.',
                ]);
            }
        } catch (\Throwable $e) {
            $registro->update([
                'estado'        => \App\Models\FelicitacionCumpleanos::ESTADO_FALLIDO,
                'error_detalle' => $e->getMessage(),
            ]);
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía YA a TODOS los cumpleañeros de hoy que no han sido felicitados
     * este año. Útil cuando no se quiere depender del scheduler.
     */
    public function enviarFelicitacionesDeHoy(): void
    {
        $anioActual = (int) now()->format('Y');

        $clientes = $this->cumpleanerosHoy
            ->filter(fn ($c) => $c->ultima_felicitacion_anio !== $anioActual);

        if ($clientes->isEmpty()) {
            $this->dispatch('notify', [
                'type'    => 'info',
                'message' => 'No hay cumpleañeros pendientes hoy (o ya fueron felicitados).',
            ]);
            return;
        }

        $enviados = 0;
        $fallidos = 0;

        foreach ($clientes as $c) {
            $this->enviarFelicitacionManual($c->id);
            // enviarFelicitacionManual ya emite sus propias notificaciones;
            // solo contamos por estado final.
            $ultimo = \App\Models\FelicitacionCumpleanos::where('cliente_id', $c->id)
                ->where('anio', $anioActual)
                ->orderByDesc('id')
                ->first();
            if ($ultimo?->estado === \App\Models\FelicitacionCumpleanos::ESTADO_ENVIADO) $enviados++;
            else $fallidos++;
            usleep(500_000);
        }

        $this->dispatch('notify', [
            'type'    => $fallidos === 0 ? 'success' : 'warning',
            'message' => "📨 {$enviados} enviados" . ($fallidos > 0 ? ", {$fallidos} fallidos" : '') . '. Mira el historial.',
        ]);
    }

    public function cargarPlantillaPorDefecto(): void
    {
        $this->system_prompt = BotPromptService::plantillaPorDefecto();
        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Plantilla por defecto cargada — recuerda guardar.',
        ]);
    }

    /**
     * Sincroniza el prompt de la BD con la plantilla actual de fábrica
     * (BotPromptService::plantillaPorDefecto). Hace TODO en un solo paso:
     *   1. Carga la plantilla al textarea
     *   2. La guarda en BD inmediatamente
     *   3. Limpia caché del bot
     *
     * Útil cuando se actualiza el código y se quiere "resetear" el prompt.
     */
    public function sincronizarConPlantillaDeFabrica(): void
    {
        $plantilla = BotPromptService::plantillaPorDefecto();

        $cfg = ConfiguracionBot::actual();
        $cfg->update([
            'system_prompt'             => $plantilla,
            'usar_prompt_personalizado' => true,
        ]);

        // Refrescar el estado local de la vista
        $this->system_prompt             = $plantilla;
        $this->usar_prompt_personalizado = true;

        ConfiguracionBot::limpiarCache();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✅ Prompt sincronizado con la plantilla de fábrica y guardado.',
        ]);
    }

    protected function rules(): array
    {
        return [
            'enviar_imagenes_productos' => 'boolean',
            'transcribir_audios'        => 'boolean',
            'max_imagenes_por_mensaje'  => 'integer|min:1|max:10',
            'enviar_imagen_destacados'  => 'boolean',
            'saludar_con_promociones'   => 'boolean',
            'derivacion_activa'                    => 'boolean',
            'derivacion_fallback_activa'           => 'boolean',
            'derivacion_instrucciones_ia'          => 'nullable|string|max:10000',
            'derivacion_frases_deteccion'          => 'nullable|string|max:2000',
            'derivacion_departamento_fallback_id'  => 'nullable|integer|exists:departamentos,id',
            'agrupar_mensajes_activo'   => 'boolean',
            'agrupar_mensajes_segundos' => 'integer|min:1|max:30',
            'modelo_openai'             => 'required|string|max:60',
            'temperatura'               => 'numeric|min:0|max:2',
            'max_tokens'                => 'integer|min:100|max:4000',
            'nombre_asesora'            => 'required|string|max:60',
            'frase_bienvenida'          => 'nullable|string|max:500',
            'info_empresa'              => 'nullable|string|max:2000',
            'activo'                    => 'boolean',
            'usar_prompt_personalizado' => 'boolean',
            'system_prompt'             => 'nullable|string|max:20000',
            'instrucciones_extra'       => 'nullable|string|max:20000',
            'cumpleanos_activo'            => 'boolean',
            'cumpleanos_hora'              => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'cumpleanos_mensaje'           => 'nullable|string|max:2000',
            'cumpleanos_dias_anticipacion' => 'integer|min:0|max:30',
            'cumpleanos_reintentos_max'    => 'integer|min:0|max:5',
            'cumpleanos_ventana_desde'     => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'cumpleanos_ventana_hasta'     => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'cumpleanos_dias_semana_arr'   => 'array|size:7',
            'connection_id_default'                 => 'nullable|integer',
            'cumpleanos_dias_vigencia_beneficio'    => 'integer|min:1|max:30',
        ];
    }

    /**
     * Guarda el toggle de transcripción apenas se clickea (wire:model.live).
     */
    public function updatedTranscribirAudios(bool $value): void
    {
        ConfiguracionBot::actual()->update(['transcribir_audios' => $value]);
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $value
                ? '🎤 Transcripción de audios ACTIVADA'
                : '🔇 Transcripción de audios DESACTIVADA',
        ]);
    }

    public function guardar(): void
    {
        $data = $this->validate();

        // Convertir array de booleans a string '1010111'
        if (isset($data['cumpleanos_dias_semana_arr'])) {
            $diasStr = '';
            foreach ($data['cumpleanos_dias_semana_arr'] as $activo) {
                $diasStr .= $activo ? '1' : '0';
            }
            $data['cumpleanos_dias_semana'] = $diasStr;
            unset($data['cumpleanos_dias_semana_arr']);
        }

        $cfg = ConfiguracionBot::actual();
        $cfg->update($data);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Configuración del bot guardada.',
        ]);
    }

    public function render()
    {
        // Conexiones de WhatsApp detectadas (sacadas de las conversaciones)
        $conexionesDetectadas = \App\Models\ConversacionWhatsapp::query()
            ->whereNotNull('connection_id')
            ->select('connection_id')
            ->distinct()
            ->orderBy('connection_id')
            ->pluck('connection_id')
            ->filter()
            ->values()
            ->toArray();

        return view('livewire.configuracion.bot', [
            'variablesDisponibles' => BotPromptService::variablesDisponibles(),
            'conexionesDetectadas' => $conexionesDetectadas,
        ])->layout('layouts.app');
    }
}
