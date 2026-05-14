<?php

namespace App\Services\Bots;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Models\Sede;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🗺️ AGENTE ESPECIALIZADO EN VALIDACIÓN DE COBERTURA
 *
 * Servicio determinista que se invoca cuando el cliente da una dirección
 * para despacho. Su único trabajo:
 *
 *   1. Tomar la dirección, barrio y ciudad del estado.
 *   2. Validar cobertura llamando al método interno del controller
 *      (que hace geocoding + cálculo de distancia a sedes).
 *   3. Persistir el resultado en el estado.
 *   4. Devolver una decisión clara al Router:
 *        - 'cubierta'           → OK, continuar flujo
 *        - 'fuera_de_cobertura' → respuesta hardcoded ofreciendo sede
 *        - 'sede_cerrada'       → respuesta específica
 *        - 'no_aplica'          → no hay dirección o no es despacho
 *
 * Beneficios:
 *   - No depende del LLM (que a veces dice "voy a validar" pero no lo hace).
 *   - Determinista: misma entrada = misma salida.
 *   - Sin tokens consumidos.
 *   - Resuelve UN problema, lo resuelve bien.
 */
class AgenteCoberturaService
{
    /**
     * Evalúa el estado y, si aplica, ejecuta la validación de cobertura.
     *
     * @return array{
     *   accion: 'cubierta'|'fuera_de_cobertura'|'sede_cerrada'|'no_aplica',
     *   reply: ?string,
     *   resultado: ?array,
     * }
     */
    public function evaluar(ConversacionWhatsapp $conv, ?int $connectionId = null): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);

        // Solo aplica para despacho con dirección, sin cobertura validada
        if ($estado->metodo_entrega !== ConversacionPedidoEstado::METODO_DOMICILIO) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }
        if (empty($estado->direccion)) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }
        if ($estado->cobertura_validada) {
            return ['accion' => 'no_aplica', 'reply' => null, 'resultado' => null];
        }

        $direccion = (string) $estado->direccion;
        $barrio    = (string) ($estado->barrio ?? '');
        $ciudad    = (string) ($estado->ciudad ?? '');

        // 🛡️ ANTI-AMBIGÜEDAD: si la dirección es un patrón colombiano de calle/carrera
        //    SIN ciudad ni barrio especificados, la misma dirección puede existir en
        //    decenas de municipios (ej. "Calle 49 # 50-05" está en Bello, Rionegro,
        //    Medellín, etc.). Pedimos clarificación al cliente ANTES de geocodificar
        //    para no decirle "cubierto" o "fuera" por una geocodificación azarosa.
        if ($ciudad === '' && $barrio === '' && $this->direccionEsAmbiguaSinContexto($direccion)) {
            $ciudadesCubiertas = $this->ciudadesQueCubrimos($connectionId);
            $listaCiudades = $ciudadesCubiertas
                ? implode(', ', array_slice($ciudadesCubiertas, 0, 6))
                : 'Bello, Girardota, etc.';
            $reply = "Para validar tu cobertura necesito saber el municipio o barrio 🙏\n\n"
                   . "*¿En qué ciudad / barrio queda* `{$direccion}`?\n\n"
                   . "(Cubrimos: {$listaCiudades})";
            Log::info('🗺️ AgenteCoberturaService: dirección ambigua, pidiendo ciudad', [
                'conv_id'  => $conv->id,
                'direccion'=> $direccion,
            ]);
            return [
                'accion'    => 'necesita_ciudad',
                'reply'     => $reply,
                'resultado' => null,
            ];
        }

        // Si no dio ciudad pero la dirección NO es ambigua (ej. incluye "Bello" en el
        // texto), asumimos la ciudad por defecto de la sede.
        if ($ciudad === '') {
            $ciudad = 'Bello'; // ciudad por defecto del tenant principal
        }

        Log::info('🗺️ AgenteCoberturaService: validando cobertura sin LLM', [
            'conv_id'  => $conv->id,
            'direccion'=> $direccion,
            'ciudad'   => $ciudad,
        ]);

        // Resolver sede actual y ejecutar validación reutilizando lógica del controller
        $sedeId = $this->resolverSedeId($connectionId);

        try {
            $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
            $reflexion = new \ReflectionMethod($controller, 'validarCoberturaDireccion');
            $reflexion->setAccessible(true);
            $resultado = $reflexion->invoke(
                $controller,
                $direccion,
                $barrio,
                $ciudad,
                $sedeId,
                $conv->telefono_normalizado
            );
        } catch (\Throwable $e) {
            Log::warning('🗺️ AgenteCoberturaService: falló validación: ' . $e->getMessage());
            return [
                'accion'    => 'no_aplica',
                'reply'     => null,
                'resultado' => null,
            ];
        }

        // 🛡️ VERIFICACIÓN POST-GEOCODING: si Google resolvió la dirección a una
        //    ciudad que NO está en nuestra cobertura (ej "Rionegro"), aunque
        //    el polígono diga "fuera", podría ser que el cliente vive en una
        //    ciudad cubierta pero Google escogió la incorrecta por ambigüedad.
        //    Pedir confirmación antes de decir "fuera de cobertura".
        if (!($resultado['cubierta'] ?? false)) {
            $display = (string) ($resultado['coordenadas']['display'] ?? '');
            $ciudadResuelta = $this->extraerCiudadDeDisplay($display);
            $ciudadesCubiertas = $this->ciudadesQueCubrimos($connectionId);
            $resueltaEsCubierta = $ciudadesCubiertas && in_array(mb_strtolower($ciudadResuelta), array_map('mb_strtolower', $ciudadesCubiertas), true);

            // Si Google resolvió a ciudad NO cubierta y el cliente NO dio ciudad
            // explícitamente → ambiguo, pedir clarificación
            if ($display !== '' && !$resueltaEsCubierta && $estado->ciudad === null) {
                $listaCiudades = $ciudadesCubiertas
                    ? implode(', ', array_slice($ciudadesCubiertas, 0, 6))
                    : 'Bello, Girardota, etc.';
                $reply = "Encontré tu dirección en *{$display}*, pero no llegamos ahí 😕\n\n"
                       . "Si querías decir una de estas ciudades, dímelo: *{$listaCiudades}*.\n\n"
                       . "Si la dirección es correcta en {$ciudadResuelta}, te ofrezco *recoger en sede*.";
                Log::info('🗺️ AgenteCoberturaService: ciudad resuelta NO cubierta, pidiendo clarificación', [
                    'conv_id'        => $conv->id,
                    'direccion'      => $direccion,
                    'display_google' => $display,
                    'ciudad_resuelta'=> $ciudadResuelta,
                ]);
                return [
                    'accion'    => 'ambigua_pedir_clarificacion',
                    'reply'     => $reply,
                    'resultado' => $resultado,
                ];
            }
        }

        // Persistir resultado
        try {
            app(EstadoPedidoService::class)->captarCobertura($conv, $resultado);
        } catch (\Throwable $e) {
            Log::warning('🗺️ AgenteCoberturaService: no pudo persistir cobertura: ' . $e->getMessage());
        }

        $cubierta = (bool) ($resultado['cubierta'] ?? false);

        if ($cubierta) {
            Log::info('✅ AgenteCoberturaService: cobertura OK', [
                'conv_id'      => $conv->id,
                'sede'         => $resultado['sede_sugerida'] ?? null,
                'distancia_km' => $resultado['distancia_km'] ?? null,
            ]);
            return [
                'accion'    => 'cubierta',
                'reply'     => null, // continúa flujo, no envía mensaje
                'resultado' => $resultado,
            ];
        }

        // Fuera de cobertura → respuesta determinista
        $sedesAbiertas = $this->sedesAbiertasResumen();
        $reply = $this->construirRespuestaFueraCobertura($direccion, $sedesAbiertas);

        Log::warning('🚫 AgenteCoberturaService: dirección fuera de cobertura', [
            'conv_id'  => $conv->id,
            'direccion'=> $direccion,
            'mensaje'  => $resultado['mensaje_sugerido'] ?? null,
        ]);

        return [
            'accion'    => 'fuera_de_cobertura',
            'reply'     => $reply,
            'resultado' => $resultado,
        ];
    }

    /**
     * 🛡️ ¿La dirección es un patrón colombiano de calle/carrera/etc SIN
     * indicación de ciudad o barrio? Si es así, la misma dirección puede
     * existir en muchos municipios → necesitamos clarificación.
     *
     * Ej: "Calle 49 # 50 - 05" (ambigua — está en Bello, Rionegro, etc.)
     *     "Calle 49 # 50 - 05 Bello" (NO ambigua — tiene la ciudad)
     */
    private function direccionEsAmbiguaSinContexto(string $direccion): bool
    {
        $d = mb_strtolower(\Illuminate\Support\Str::ascii(trim($direccion)));
        if ($d === '') return false;

        // ¿Es un patrón de vía colombiana? (calle/cra/dg/tv/av + número)
        $patronVia = '/\b(cra|carrera|kr|cr|cl|calle|cll|dg|diagonal|trv|transversal|tv|av|avenida|circular)\s*\.?\s*\d/iu';
        if (!preg_match($patronVia, $d)) return false;

        // ¿Menciona alguna ciudad/barrio conocidos del Valle de Aburrá o cercanos?
        // Si no menciona ninguno → ambigua.
        $municipiosBarrios = [
            // Valle de Aburrá
            'bello', 'medellin', 'medellín', 'girardota', 'copacabana', 'sabaneta',
            'envigado', 'itagui', 'itagüí', 'caldas', 'la estrella', 'barbosa',
            // Otras ciudades comunes
            'rionegro', 'marinilla', 'guarne', 'la ceja', 'el retiro', 'el carmen',
            'apartado', 'turbo', 'cali', 'bogota', 'bogotá', 'cartagena', 'barranquilla',
            'santa marta', 'pereira', 'manizales', 'ibague', 'ibagué',
            // Barrios populares de Bello
            'prado', 'niquia', 'niquía', 'fontidueño', 'rincon santo', 'cabañas',
            'paris', 'parís', 'la gabriela', 'altamira', 'la mota',
        ];
        foreach ($municipiosBarrios as $loc) {
            if (str_contains($d, $loc)) return false;
        }

        return true;
    }

    /**
     * 🗺️ Lista de ciudades cubiertas por las sedes activas del tenant.
     * Se infiere del nombre de las zonas guardadas en cobertura_zonas_nombres
     * o, si no hay nombres, de los centros de los polígonos vía reverse-geocoding.
     * Para simplicidad, devolvemos una lista hardcoded del Valle de Aburrá
     * que el operador puede confirmar visualmente en el editor de cobertura.
     */
    private function ciudadesQueCubrimos(?int $connectionId): array
    {
        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();
            if (!$tenantId) return [];

            $sedes = \App\Models\Sede::where('tenant_id', $tenantId)
                ->where('activa', true)
                ->where('cobertura_activa', true)
                ->get();

            $ciudades = [];
            foreach ($sedes as $s) {
                if (!$s->tieneCobertura()) continue;
                $polys = $s->poligonosNormalizados();
                foreach ($polys as $poly) {
                    $lats = array_column($poly, 0);
                    $lngs = array_column($poly, 1);
                    if (empty($lats)) continue;
                    $cLat = (min($lats) + max($lats)) / 2;
                    $cLng = (min($lngs) + max($lngs)) / 2;

                    // Heurística por bbox: identificar municipio por proximidad al centro
                    $municipio = $this->municipioPorCoordenadas($cLat, $cLng);
                    if ($municipio) $ciudades[$municipio] = true;
                }
            }
            return array_keys($ciudades);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Heurística simple: dado un lat/lng, devolver el municipio aproximado
     * del Valle de Aburrá. Cubrimos los principales.
     */
    private function municipioPorCoordenadas(float $lat, float $lng): ?string
    {
        $municipios = [
            ['Bello',       6.336, -75.559, 0.06],
            ['Medellín',    6.244, -75.581, 0.10],
            ['Girardota',   6.376, -75.445, 0.08],
            ['Copacabana',  6.348, -75.512, 0.05],
            ['Sabaneta',    6.150, -75.616, 0.04],
            ['Envigado',    6.169, -75.583, 0.05],
            ['Itagüí',      6.171, -75.611, 0.05],
            ['La Estrella', 6.155, -75.643, 0.05],
            ['Caldas',      6.090, -75.640, 0.06],
            ['Barbosa',     6.439, -75.330, 0.07],
        ];
        foreach ($municipios as [$nombre, $mLat, $mLng, $tol]) {
            if (abs($lat - $mLat) <= $tol && abs($lng - $mLng) <= $tol) {
                return $nombre;
            }
        }
        return null;
    }

    /**
     * Extrae el nombre del municipio del display_name que devuelve Google.
     * Ej: "Calle 49, Suárez, Bello, Valle de Aburrá..." → "Bello"
     */
    private function extraerCiudadDeDisplay(string $display): string
    {
        if ($display === '') return '';
        $partes = array_map('trim', explode(',', $display));
        // Suele ser: "calle, barrio, MUNICIPIO, region, depto, pais"
        // Buscamos un municipio conocido
        $conocidos = ['Bello', 'Medellín', 'Girardota', 'Copacabana', 'Sabaneta',
                      'Envigado', 'Itagüí', 'Itagui', 'La Estrella', 'Caldas',
                      'Barbosa', 'Rionegro', 'Marinilla', 'Guarne', 'La Ceja'];
        foreach ($partes as $p) {
            foreach ($conocidos as $c) {
                if (strcasecmp($p, $c) === 0) return $c;
            }
        }
        // Fallback: tercer elemento (típicamente el municipio)
        return $partes[2] ?? ($partes[1] ?? '');
    }

    private function resolverSedeId(?int $connectionId): ?int
    {
        if ($connectionId) {
            try {
                $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
                $rm = new \ReflectionMethod($controller, 'obtenerSedeIdDesdeConexion');
                $rm->setAccessible(true);
                $id = $rm->invoke($controller, $connectionId);
                if ($id) return $id;
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $primera = Sede::where('activa', true)->first();
        return $primera?->id;
    }

    private function sedesAbiertasResumen(): array
    {
        return Sede::where('activa', true)->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'nombre'     => $s->nombre,
                'direccion'  => $s->direccion ?? null,
                'abierta'    => $s->estaAbierta(),
            ])
            ->all();
    }

    private function construirRespuestaFueraCobertura(string $direccion, array $sedes): string
    {
        $abiertas = collect($sedes)->where('abierta', true)->values();
        $hayAbiertas = $abiertas->isNotEmpty();

        $msg = "Lo siento 🙏, la dirección *{$direccion}* está fuera de nuestra zona de cobertura para domicilio.\n\n";

        if ($hayAbiertas) {
            $msg .= "*¿Qué te ofrezco?*\n";
            $msg .= "1. *Recoger en sede* — tenemos disponible:\n";
            foreach ($abiertas->take(3) as $s) {
                $dir = $s['direccion'] ? " · {$s['direccion']}" : '';
                $msg .= "   • *{$s['nombre']}*{$dir}\n";
            }
            $msg .= "2. *Otra dirección* dentro de nuestra zona — pásame y la valido.\n";
        } else {
            $msg .= "Por ahora todas nuestras sedes están cerradas. Cuando vuelvan a abrir podremos coordinar tu pedido.";
        }

        return $msg;
    }
}
