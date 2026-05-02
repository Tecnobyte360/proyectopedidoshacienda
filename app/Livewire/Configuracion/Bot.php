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

    // Zonas de cobertura con las que opera el bot (vacío = todas las activas)
    public array $bot_zonas_ids = [];

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

    // Encuesta post-entrega
    public bool   $encuesta_activa          = true;
    public int    $encuesta_delay_minutos   = 15;
    public string $encuesta_mensaje         = '';

    // Pagos en línea (Wompi)
    public bool   $enviar_link_pago         = true;

    // Toggles de notificaciones al cliente
    public bool   $notif_en_preparacion_activa = true;
    public bool   $notif_en_camino_activa      = true;
    public bool   $notif_entregado_activa      = true;
    public bool   $notif_pago_aprobado_activa  = true;
    public bool   $notif_pago_rechazado_activa = true;

    // Plantillas + delays editables por tipo
    public string $notif_pedido_confirmado_mensaje = '';
    public string $notif_en_preparacion_mensaje = '';
    public string $notif_en_camino_mensaje      = '';
    public string $notif_entregado_mensaje      = '';
    public string $notif_pago_aprobado_mensaje  = '';
    public string $notif_pago_rechazado_mensaje = '';

    public int $notif_en_preparacion_delay = 0;
    public int $notif_en_camino_delay      = 0;
    public int $notif_entregado_delay      = 0;
    public int $notif_pago_aprobado_delay  = 0;
    public int $notif_pago_rechazado_delay = 0;

    // Fuente de productos del bot
    public string $fuente_productos          = 'tabla'; // tabla | integracion
    public ?int   $integracion_productos_id  = null;
    public int    $auto_sync_productos_min   = 15;
    public ?string $ultimo_sync_productos_at = null;

    // Filtros del catalogo del bot
    public string $categorias_excluidas_bot_str = '';      // textarea (una por linea)
    public bool   $excluir_productos_sin_precio = true;
    public bool   $bot_modo_agente              = false;

    // Solicitar cedula al cliente
    public bool   $pedir_cedula        = false;
    public bool   $cedula_obligatoria  = false;
    public string $cedula_descripcion  = '';
    public ?int   $cedula_consulta_id  = null;

    // Auto-asignación de domiciliarios
    public bool   $auto_asignar_domiciliario = false;
    public string $criterio_asignacion       = 'balanceado';
    public string $asignar_en_estado         = 'en_preparacion';

    // Editor de prompt por bloques
    public bool  $vistaPorBloques = true;
    public array $bloquesPrompt   = [];   // [{titulo, contenido}]

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

        $this->bot_zonas_ids = collect($cfg->bot_zonas_ids ?? [])
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

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

        $this->encuesta_activa        = (bool) ($cfg->encuesta_activa ?? true);
        $this->encuesta_delay_minutos = (int) ($cfg->encuesta_delay_minutos ?? 15);
        $this->encuesta_mensaje       = (string) ($cfg->encuesta_mensaje ?? '');
        $this->enviar_link_pago       = (bool) ($cfg->enviar_link_pago ?? true);
        $this->notif_en_preparacion_activa = (bool) ($cfg->notif_en_preparacion_activa ?? true);
        $this->notif_en_camino_activa      = (bool) ($cfg->notif_en_camino_activa ?? true);
        $this->notif_entregado_activa      = (bool) ($cfg->notif_entregado_activa ?? true);
        $this->notif_pago_aprobado_activa  = (bool) ($cfg->notif_pago_aprobado_activa ?? true);
        $this->notif_pago_rechazado_activa = (bool) ($cfg->notif_pago_rechazado_activa ?? true);

        $D = ConfiguracionBot::NOTIF_DEFAULTS;
        $this->notif_pedido_confirmado_mensaje = (string) ($cfg->notif_pedido_confirmado_mensaje ?: $D['pedido_confirmado']);
        $this->notif_en_preparacion_mensaje = (string) ($cfg->notif_en_preparacion_mensaje ?: $D['en_preparacion']);
        $this->notif_en_camino_mensaje      = (string) ($cfg->notif_en_camino_mensaje      ?: $D['en_camino']);
        $this->notif_entregado_mensaje      = (string) ($cfg->notif_entregado_mensaje      ?: $D['entregado']);
        $this->notif_pago_aprobado_mensaje  = (string) ($cfg->notif_pago_aprobado_mensaje  ?: $D['pago_aprobado']);
        $this->notif_pago_rechazado_mensaje = (string) ($cfg->notif_pago_rechazado_mensaje ?: $D['pago_rechazado']);

        $this->notif_en_preparacion_delay = (int) ($cfg->notif_en_preparacion_delay ?? 0);
        $this->notif_en_camino_delay      = (int) ($cfg->notif_en_camino_delay ?? 0);
        $this->notif_entregado_delay      = (int) ($cfg->notif_entregado_delay ?? 0);
        $this->notif_pago_aprobado_delay  = (int) ($cfg->notif_pago_aprobado_delay ?? 0);
        $this->notif_pago_rechazado_delay = (int) ($cfg->notif_pago_rechazado_delay ?? 0);

        // Fuente de productos
        $this->fuente_productos          = $cfg->fuente_productos ?: 'tabla';
        $this->integracion_productos_id  = $cfg->integracion_productos_id;
        $this->auto_sync_productos_min   = (int) ($cfg->auto_sync_productos_min ?: 15);
        $this->ultimo_sync_productos_at  = $cfg->ultimo_sync_productos_at?->format('Y-m-d H:i:s');

        $this->categorias_excluidas_bot_str = collect($cfg->categorias_excluidas_bot ?? [])
            ->filter()->implode("\n");
        $this->excluir_productos_sin_precio = (bool) ($cfg->excluir_productos_sin_precio ?? true);
        $this->bot_modo_agente              = (bool) ($cfg->bot_modo_agente ?? false);

        $this->pedir_cedula        = (bool) ($cfg->pedir_cedula ?? false);
        $this->cedula_obligatoria  = (bool) ($cfg->cedula_obligatoria ?? false);
        $this->cedula_descripcion  = (string) ($cfg->cedula_descripcion ?? '');
        $this->cedula_consulta_id  = $cfg->cedula_consulta_id;
        $this->auto_asignar_domiciliario = (bool) ($cfg->auto_asignar_domiciliario ?? false);
        $this->criterio_asignacion       = (string) ($cfg->criterio_asignacion ?: 'balanceado');
        $this->asignar_en_estado         = (string) ($cfg->asignar_en_estado ?: 'en_preparacion');

        // Parsear el system_prompt en bloques editables
        $this->bloquesPrompt = $this->parsearBloques($this->system_prompt);
    }

    /**
     * Convierte el prompt plano en un array de bloques separados por
     * encabezados "# TÍTULO". Cada bloque queda como
     *   ['titulo' => 'IDENTIDAD', 'contenido' => 'Eres ...']
     * Las líneas decorativas (═══) se ignoran.
     */
    public function parsearBloques(string $prompt): array
    {
        if (trim($prompt) === '') {
            return [
                ['titulo' => 'IDENTIDAD',         'contenido' => ''],
                ['titulo' => 'CONTEXTO',          'contenido' => '{empresa}'],
                ['titulo' => 'CATÁLOGO',          'contenido' => '{catalogo}'],
                ['titulo' => 'PROMOCIONES',       'contenido' => '{promociones}'],
                ['titulo' => 'HORARIOS Y ZONAS',  'contenido' => "Estado: {sede_estado_actual}\n{horarios_sedes}\n\n{zonas}"],
                ['titulo' => 'ANS',               'contenido' => '{ans}'],
                ['titulo' => 'REGLAS',            'contenido' => ''],
            ];
        }

        // Eliminar líneas decorativas (═══)
        $limpio = preg_replace('/^[═─━_]{3,}\s*$/m', '', $prompt);

        $bloques = [];
        $cursor = ['titulo' => 'GENERAL', 'contenido' => ''];
        $primero = true;

        foreach (explode("\n", $limpio) as $linea) {
            // Detecta encabezado tipo "# TÍTULO" o "## TÍTULO"
            if (preg_match('/^#{1,3}\s+(.+?)\s*$/', $linea, $m)) {
                if (!$primero || trim($cursor['contenido']) !== '') {
                    $cursor['contenido'] = trim($cursor['contenido']);
                    $bloques[] = $cursor;
                }
                $cursor = ['titulo' => trim($m[1]), 'contenido' => ''];
                $primero = false;
                continue;
            }
            $cursor['contenido'] .= $linea . "\n";
        }
        $cursor['contenido'] = trim($cursor['contenido']);
        if ($cursor['contenido'] !== '' || trim($cursor['titulo']) !== 'GENERAL') {
            $bloques[] = $cursor;
        }

        return $bloques;
    }

    /**
     * Concatena los bloques en un prompt plano con los separadores
     * y encabezados que la IA espera.
     */
    public function serializarBloques(array $bloques): string
    {
        $partes = [];
        foreach ($bloques as $b) {
            $titulo    = trim($b['titulo'] ?? '');
            $contenido = trim($b['contenido'] ?? '');
            if ($titulo === '' && $contenido === '') continue;

            $sep = str_repeat('═', 79);
            if ($titulo !== '') {
                $partes[] = "# {$titulo}";
            }
            if ($contenido !== '') {
                $partes[] = $contenido;
            }
            $partes[] = $sep;
        }

        // Quitar último separador
        if (end($partes) === str_repeat('═', 79)) array_pop($partes);

        return implode("\n\n", $partes);
    }

    /* ─── Acciones de bloques ─── */
    public function agregarBloque(): void
    {
        $this->bloquesPrompt[] = ['titulo' => 'NUEVA SECCIÓN', 'contenido' => ''];
    }

    public function eliminarBloque(int $idx): void
    {
        unset($this->bloquesPrompt[$idx]);
        $this->bloquesPrompt = array_values($this->bloquesPrompt);
    }

    public function moverBloque(int $idx, int $delta): void
    {
        $nuevoIdx = $idx + $delta;
        if (!isset($this->bloquesPrompt[$idx]) || !isset($this->bloquesPrompt[$nuevoIdx])) return;
        [$this->bloquesPrompt[$idx], $this->bloquesPrompt[$nuevoIdx]] =
            [$this->bloquesPrompt[$nuevoIdx], $this->bloquesPrompt[$idx]];
    }

    public function sincronizarBloquesAPrompt(): void
    {
        $this->system_prompt = $this->serializarBloques($this->bloquesPrompt);
    }

    public function sincronizarPromptABloques(): void
    {
        $this->bloquesPrompt = $this->parsearBloques($this->system_prompt);
    }

    /** Cambia a vista por bloques sincronizando el prompt actual */
    public function activarVistaBloques(): void
    {
        $this->bloquesPrompt = $this->parsearBloques($this->system_prompt);
        $this->vistaPorBloques = true;
    }

    /** Cambia a vista plana sincronizando los bloques actuales */
    public function activarVistaPlana(): void
    {
        $this->system_prompt = $this->serializarBloques($this->bloquesPrompt);
        $this->vistaPorBloques = false;
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
        $this->bloquesPrompt = $this->parsearBloques($this->system_prompt);
        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Plantilla por defecto cargada — recuerda guardar.',
        ]);
    }

    /**
     * Carga la plantilla GENÉRICA dinámica (sin texto hardcoded).
     * Lee solo de variables del tenant — funciona con cualquier negocio.
     */
    public function cargarPlantillaGenerica(): void
    {
        $this->system_prompt = BotPromptService::plantillaGenerica();
        $this->bloquesPrompt = $this->parsearBloques($this->system_prompt);
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Plantilla genérica cargada. Funciona con cualquier tenant. Guarda para aplicar.',
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
            'bot_zonas_ids'             => 'array',
            'bot_zonas_ids.*'           => 'integer|exists:zonas_cobertura,id',
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
            'encuesta_activa'                       => 'boolean',
            'encuesta_delay_minutos'                => 'integer|min:0|max:1440',
            'encuesta_mensaje'                      => 'nullable|string|max:2000',
            'enviar_link_pago'                      => 'boolean',
            'notif_en_preparacion_activa'           => 'boolean',
            'notif_en_camino_activa'                => 'boolean',
            'notif_entregado_activa'                => 'boolean',
            'notif_pago_aprobado_activa'            => 'boolean',
            'notif_pago_rechazado_activa'           => 'boolean',
            'notif_pedido_confirmado_mensaje'       => 'nullable|string|max:3000',
            'notif_en_preparacion_mensaje'          => 'nullable|string|max:2000',
            'notif_en_camino_mensaje'               => 'nullable|string|max:2000',
            'notif_entregado_mensaje'               => 'nullable|string|max:2000',
            'notif_pago_aprobado_mensaje'           => 'nullable|string|max:2000',
            'notif_pago_rechazado_mensaje'          => 'nullable|string|max:2000',
            'notif_en_preparacion_delay'            => 'integer|min:0|max:86400',
            'notif_en_camino_delay'                 => 'integer|min:0|max:86400',
            'notif_entregado_delay'                 => 'integer|min:0|max:86400',
            'notif_pago_aprobado_delay'             => 'integer|min:0|max:86400',
            'notif_pago_rechazado_delay'            => 'integer|min:0|max:86400',
            'fuente_productos'                      => 'required|in:tabla,integracion',
            'integracion_productos_id'              => 'nullable|integer|exists:integraciones,id',
            'auto_sync_productos_min'               => 'integer|min:1|max:1440',
            'categorias_excluidas_bot_str'          => 'nullable|string',
            'excluir_productos_sin_precio'          => 'boolean',
            'bot_modo_agente'                       => 'boolean',
            'pedir_cedula'                          => 'boolean',
            'cedula_obligatoria'                    => 'boolean',
            'cedula_descripcion'                    => 'nullable|string|max:300',
            'cedula_consulta_id'                    => 'nullable|integer|exists:integracion_consultas,id',
            'auto_asignar_domiciliario'             => 'boolean',
            'criterio_asignacion'                   => 'nullable|in:balanceado,cercania,rotacion',
            'asignar_en_estado'                     => 'nullable|in:nuevo,en_preparacion,repartidor_en_camino',
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

    public ?string $catalogoPreview = null;
    public ?array  $catalogoPreviewMeta = null;

    public function cargarSugerenciasExclusion(): void
    {
        // Categorias tipicas que NO son comida y suelen llenar el ERP
        $sugeridas = [
            'GENERAL',
            'SERVICIOS Y OTROS',
            'INSUMOS Y MP',
            'BOLSAS Y EMPAQUES',
            'EMBUTIDOS',
            'PETS',
        ];
        $actuales = collect(preg_split('/\R+/', $this->categorias_excluidas_bot_str))
            ->map(fn ($l) => trim($l))->filter()->all();
        $combinadas = collect($actuales)->concat($sugeridas)->map(fn ($c) => trim($c))
            ->unique()->values()->all();
        $this->categorias_excluidas_bot_str = implode("\n", $combinadas);

        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Sugerencias agregadas. No olvides Guardar.']);
    }

    public function verCatalogoBot(): void
    {
        try {
            // Forzar lectura fresca: limpiar el cache antes
            app(\App\Services\BotCatalogoService::class)->limpiarCache();

            $service = app(\App\Services\BotCatalogoService::class);
            $productos = $service->productosActivos();
            $this->catalogoPreview = $service->catalogoFormateado();

            // Meta: cuantos hay y de cada fuente (modo integracion)
            $fuentes = $productos->groupBy(fn ($p) => $p->_fuente ?? '—')->map->count();
            $this->catalogoPreviewMeta = [
                'total'   => $productos->count(),
                'fuentes' => $fuentes->toArray(),
            ];
        } catch (\Throwable $e) {
            $this->catalogoPreview = "❌ Error: " . $e->getMessage();
            $this->catalogoPreviewMeta = null;
        }
    }

    public function sincronizarProductosAhora(): void
    {
        if ($this->fuente_productos !== 'integracion') {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Selecciona "Integración externa" antes de sincronizar.']);
            return;
        }
        if (!$this->integracion_productos_id) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Selecciona una integración del listado.']);
            return;
        }

        try {
            // Persistir la seleccion actual antes de sincronizar (para que la
            // UI no requiera "Guardar" manualmente para activar la integracion).
            $cfg = ConfiguracionBot::actual();
            $cfg->update([
                'fuente_productos'         => $this->fuente_productos,
                'integracion_productos_id' => $this->integracion_productos_id,
                'auto_sync_productos_min'  => $this->auto_sync_productos_min,
            ]);

            $integracion = \App\Models\Integracion::findOrFail($this->integracion_productos_id);
            if (!$integracion->activo) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'La integración seleccionada está inactiva.']);
                return;
            }

            $r = app(\App\Services\IntegracionSyncService::class)->sincronizar($integracion);

            $cfg->update(['ultimo_sync_productos_at' => now()]);
            app(\App\Services\BotCatalogoService::class)->limpiarCache();

            $this->ultimo_sync_productos_at = now()->format('Y-m-d H:i:s');

            if ($r['ok'] ?? false) {
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => "✓ Sync OK: {$r['creados']} creados, {$r['actualizados']} actualizados.",
                ]);
            } else {
                $this->dispatch('notify', ['type' => 'error', 'message' => '❌ ' . ($r['mensaje'] ?? $r['log'] ?? 'Error en sync')]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '❌ ' . $e->getMessage()]);
        }
    }

    public function guardar(): void
    {
        // Si está en vista por bloques, regenerar el system_prompt antes de validar
        if ($this->vistaPorBloques) {
            $this->system_prompt = $this->serializarBloques($this->bloquesPrompt);
        }

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

        // Normalizar zonas: vacío = todas
        $data['bot_zonas_ids'] = collect($data['bot_zonas_ids'] ?? [])
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        // No persistir el campo readonly de UI
        unset($data['ultimo_sync_productos_at']);

        // Convertir el textarea de categorias excluidas a array (una por linea)
        if (isset($data['categorias_excluidas_bot_str'])) {
            $data['categorias_excluidas_bot'] = collect(preg_split('/\R+/', (string) $data['categorias_excluidas_bot_str']))
                ->map(fn ($l) => trim($l))
                ->filter()
                ->unique()
                ->values()
                ->all();
            unset($data['categorias_excluidas_bot_str']);
        }

        $cfg = ConfiguracionBot::actual();
        $cfg->update($data);

        // Limpiar caché del catálogo (zonas formateadas) para que refresque
        app(\App\Services\BotCatalogoService::class)->limpiarCache();

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

        $zonasDisponibles = \App\Models\ZonaCobertura::with('sede:id,nombre')
            ->where('activa', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sede_id', 'orden']);

        $integracionesProductos = \App\Models\Integracion::where('entidad', \App\Models\Integracion::ENTIDAD_PRODUCTOS)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        // Consultas dinamicas activas y expuestas al bot — para vincular a la regla de cedula
        $consultasDisponibles = \App\Models\IntegracionConsulta::query()
            ->where('activa', true)
            ->where('usar_en_bot', true)
            ->orderBy('nombre_publico')
            ->get(['id', 'nombre_publico', 'nombre', 'tipo']);

        return view('livewire.configuracion.bot', [
            'variablesDisponibles' => BotPromptService::variablesDisponibles(),
            'conexionesDetectadas' => $conexionesDetectadas,
            'zonasDisponibles'     => $zonasDisponibles,
            'integracionesProductos' => $integracionesProductos,
            'consultasDisponibles'   => $consultasDisponibles,
        ])->layout('layouts.app');
    }
}
