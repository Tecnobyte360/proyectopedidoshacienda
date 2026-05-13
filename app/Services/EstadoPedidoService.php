<?php

namespace App\Services;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use Illuminate\Support\Facades\Log;

/**
 * 🎯 Servicio para gestionar el estado estructurado del pedido por conversación.
 *
 * Es el "memoria a largo plazo" del bot. Cada vez que el LLM o un guard
 * extrae un dato (producto, dirección, cédula, etc), aquí se guarda en BD.
 *
 * El método aOrderData() arma el payload de confirmar_pedido a partir
 * de esta verdad estructurada — no del JSON volátil de los tool_calls.
 */
class EstadoPedidoService
{
    /**
     * Obtiene (o crea) el estado de la conversación.
     */
    /** @var array<int,bool> Lock anti-recursión por conversación */
    private static array $obteniendo = [];

    public function obtener(ConversacionWhatsapp $conv): ConversacionPedidoEstado
    {
        $estado = ConversacionPedidoEstado::firstOrCreate(
            ['conversacion_id' => $conv->id],
            [
                'tenant_id'   => $conv->tenant_id,
                'paso_actual' => ConversacionPedidoEstado::PASO_INICIO,
                'telefono'    => $conv->telefono_normalizado,
            ]
        );

        // 🛡️ Anti-recursión: si ya estamos dentro de obtener() para esta
        // conversación (porque resetear() llama a obtener() internamente),
        // devolver el estado sin disparar auto-reset ni hidratación.
        if (!empty(self::$obteniendo[$conv->id])) {
            return $estado;
        }
        self::$obteniendo[$conv->id] = true;

        try {
            return $this->procesarObtener($conv, $estado);
        } finally {
            unset(self::$obteniendo[$conv->id]);
        }
    }

    private function procesarObtener(ConversacionWhatsapp $conv, ConversacionPedidoEstado $estado): ConversacionPedidoEstado
    {
        // 🔄 AUTO-RESET POST-PEDIDO:
        // Si el cliente tiene paso=confirmado (de un pedido anterior ya cerrado)
        // y vuelve a escribir, reseteamos el estado para que arranque un flujo
        // limpio (preservando identidad: cédula, nombre, email).
        if ($estado->paso_actual === ConversacionPedidoEstado::PASO_CONFIRMADO
            && $estado->confirmado_at
            && $estado->confirmado_at->diffInMinutes(now()) > 1) {

            Log::info('🔄 Auto-reset post-pedido: cliente vuelve tras pedido cerrado', [
                'conv_id'        => $conv->id,
                'pedido_anterior'=> $estado->pedido_id,
                'confirmado_at'  => $estado->confirmado_at?->toDateTimeString(),
            ]);
            // Reset preservando identidad (resetear llama a obtener() pero
            // el lock anti-recursión evita el ciclo infinito)
            $this->resetear($conv, "nuevo_pedido_tras_pedido_{$estado->pedido_id}");
            $estado = $estado->fresh() ?: $estado;
        }

        // 🛡️ HIDRATACIÓN CONDICIONAL desde el cliente local del NÚMERO.
        // CASO ESPECIAL: el titular del número WhatsApp puede estar haciendo
        // un pedido para OTRO cliente (ej. operador del negocio que envía
        // pedidos de terceros desde su número). En ese caso NO debemos
        // hidratar con los datos del titular — debemos esperar a que el
        // cliente nos dé los datos correctos.
        //
        // Estrategia: solo hidratar cuando el estado del pedido es
        // claramente NUEVO (sin productos, sin cédula explícita capturada
        // del mensaje actual). Una vez el cliente da una cédula distinta,
        // NUNCA volvemos a hidratar con los del titular.
        try {
            $cliente = $conv->cliente;
            $estadoVacio = empty($estado->productos) && empty($estado->cedula)
                && empty($estado->nombre_cliente) && empty($estado->direccion);
            $primeraVez = $estado->paso_actual === ConversacionPedidoEstado::PASO_INICIO;

            // Solo hidratar si el estado es completamente nuevo Y aún en paso INICIO.
            // Esto evita pisar datos que un operador esté capturando para un tercero.
            if ($cliente && $estadoVacio && $primeraVez) {
                $touched = false;
                if (empty($estado->cedula) && !empty($cliente->cedula)) {
                    $estado->cedula = $cliente->cedula;
                    $touched = true;
                }
                if (empty($estado->email) && !empty($cliente->email)) {
                    $estado->email = $cliente->email;
                    $touched = true;
                }
                if (empty($estado->nombre_cliente) && !empty($cliente->nombre)
                    && !str_contains((string) $cliente->nombre, '@')
                    && strtolower((string) $cliente->nombre) !== 'cliente'
                ) {
                    $estado->nombre_cliente = $cliente->nombre;
                    $touched = true;
                }
                if ($touched) {
                    $estado->save();
                    Log::info('🛡️ Estado hidratado con datos del cliente local', [
                        'conv_id' => $conv->id,
                        'cedula'  => $estado->cedula,
                        'email'   => $estado->email,
                        'nombre'  => $estado->nombre_cliente,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo hidratar estado desde cliente: ' . $e->getMessage());
        }

        return $estado;
    }

    /**
     * 🛡️ Si el cliente DA datos distintos (cédula, nombre, email) a los
     * hidratados del titular, asume que es un PEDIDO PARA OTRO CLIENTE
     * y resetea los datos hidratados antes de capturar los nuevos.
     */
    public function resetearSiPedidoParaTercero(ConversacionWhatsapp $conv, string $cedulaNueva): void
    {
        $estado = $this->obtener($conv);
        if (empty($estado->cedula)) return;

        $cedulaActual = preg_replace('/[^\d]/', '', (string) $estado->cedula);
        $cedulaNueva  = preg_replace('/[^\d]/', '', $cedulaNueva);
        if ($cedulaActual === $cedulaNueva) return;

        // Cédula distinta → es para otro cliente
        Log::warning('🔄 Pedido para OTRO cliente detectado — limpiando datos hidratados', [
            'conv_id'        => $conv->id,
            'cedula_antigua' => $cedulaActual,
            'cedula_nueva'   => $cedulaNueva,
        ]);

        $estado->update([
            'cedula'              => null,
            'nombre_cliente'      => null,
            'email'               => null,
            'cliente_existe_erp'  => false,
            'datos_erp'           => null,
            'direccion'           => null,
            'cobertura_validada'  => false,
            'distancia_km'        => null,
            'costo_envio'         => null,
        ]);
    }

    /**
     * Capta los datos de un orderData (proveniente de confirmar_pedido tool_call)
     * y los persiste. NO confirma el pedido — solo guarda lo que vio.
     */
    public function captarDeOrderData(ConversacionWhatsapp $conv, array $orderData): ConversacionPedidoEstado
    {
        $estado = $this->obtener($conv);

        // Productos: solo actualiza si vienen con datos válidos
        if (!empty($orderData['products'])) {
            $productosLimpios = array_values(array_filter(
                $orderData['products'],
                fn ($p) => !empty($p['name'])
            ));
            if (!empty($productosLimpios)) {
                $estado->productos = $productosLimpios;
            }
        }

        // Identificación
        if (!empty($orderData['customer_name'])) {
            $estado->nombre_cliente = trim($orderData['customer_name']);
        }
        if (!empty($orderData['cedula'])) {
            $estado->cedula = trim($orderData['cedula']);
        }
        if (!empty($orderData['phone'])) {
            $estado->telefono = trim($orderData['phone']);
        }
        if (!empty($orderData['email'])) {
            $estado->email = trim($orderData['email']);
        }

        // Entrega — detecta si es recogida o domicilio
        $esRecoger = !empty($orderData['pickup'])
            || !empty($orderData['sede_id'])
            || (empty($orderData['address']) && !empty($orderData['location']));

        if ($esRecoger) {
            $estado->metodo_entrega = ConversacionPedidoEstado::METODO_RECOGER;

            // 🎯 Resolver sede_id por varias vías:
            //   1. Si vino sede_id explícito → usar
            //   2. Si vino location con nombre de sede → buscar en BD por nombre
            //   3. Si no, dejar NULL (se asignará la default de la conexión)
            if (!empty($orderData['sede_id'])) {
                $estado->sede_id = (int) $orderData['sede_id'];
            } elseif (!empty($orderData['location'])) {
                $sedeBuscada = trim($orderData['location']);
                $sedeMatch = \App\Models\Sede::query()
                    ->where('activa', true)
                    ->where(function ($q) use ($sedeBuscada) {
                        $q->whereRaw('LOWER(nombre) = ?', [mb_strtolower($sedeBuscada)])
                          ->orWhereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($sedeBuscada) . '%']);
                    })
                    ->first();
                if ($sedeMatch) {
                    $estado->sede_id = $sedeMatch->id;
                    Log::info('🏢 Sede resuelta por nombre', [
                        'buscado' => $sedeBuscada,
                        'sede_id' => $sedeMatch->id,
                        'sede_nombre' => $sedeMatch->nombre,
                    ]);
                }
            }
        } elseif (!empty($orderData['address'])) {
            $estado->metodo_entrega = ConversacionPedidoEstado::METODO_DOMICILIO;
            $estado->direccion = trim($orderData['address']);
            if (!empty($orderData['neighborhood'])) {
                $estado->barrio = trim($orderData['neighborhood']);
            }
            if (!empty($orderData['location'])) {
                $estado->ciudad = trim($orderData['location']);
            }
        }

        if (!empty($orderData['payment_method'])) {
            $estado->metodo_pago = trim($orderData['payment_method']);
        }
        if (!empty($orderData['coupon_code'])) {
            $estado->cupon_code = trim($orderData['coupon_code']);
        }
        if (!empty($orderData['notes'])) {
            $estado->notas = trim($orderData['notes']);
        }

        $estado->save();
        $this->avanzarPaso($estado);

        Log::info('💾 Estado pedido actualizado desde orderData', [
            'conv_id' => $conv->id,
            'paso'    => $estado->paso_actual,
            'completo'=> $estado->estaCompleto(),
        ]);

        return $estado;
    }

    /**
     * Captura del resultado de validar_cobertura.
     */
    public function captarCobertura(ConversacionWhatsapp $conv, array $resultado): ConversacionPedidoEstado
    {
        $estado = $this->obtener($conv);

        if (($resultado['cubierta'] ?? false) === true) {
            $estado->cobertura_validada = true;
            $estado->metodo_entrega     = $estado->metodo_entrega ?: ConversacionPedidoEstado::METODO_DOMICILIO;

            if (!empty($resultado['sede_id'])) {
                $estado->sede_id = (int) $resultado['sede_id'];
            }
            if (isset($resultado['distancia_km'])) {
                $estado->distancia_km = (float) $resultado['distancia_km'];
            }
            if (isset($resultado['costo_envio'])) {
                $estado->costo_envio = (float) $resultado['costo_envio'];
            }
        }

        $estado->marcarValidacion('cobertura', (bool) ($resultado['cubierta'] ?? false));
        $estado->save();
        $this->avanzarPaso($estado);

        return $estado;
    }

    /**
     * Captura del resultado de verificar_cliente_erp.
     */
    public function captarClienteErp(ConversacionWhatsapp $conv, array $resultado, ?string $cedula = null): ConversacionPedidoEstado
    {
        $estado = $this->obtener($conv);

        if ($cedula && empty($estado->cedula)) {
            $estado->cedula = trim($cedula);
        }

        if (($resultado['existe'] ?? false) === true) {
            $estado->cliente_existe_erp = true;
            $estado->datos_erp = $resultado['datos'] ?? null;

            // Auto-rellenar nombre y dirección si los tenemos del ERP
            if (empty($estado->nombre_cliente) && !empty($resultado['datos']['nombre'])) {
                $estado->nombre_cliente = trim($resultado['datos']['nombre']);
            }
            if (empty($estado->direccion) && !empty($resultado['datos']['direccion'])) {
                $estado->direccion = trim($resultado['datos']['direccion']);
            }
            if (empty($estado->telefono) && !empty($resultado['datos']['telefono'])) {
                $estado->telefono = trim($resultado['datos']['telefono']);
            }
        } else {
            // Marcamos explícitamente como NO existente para que avanzarPaso
            // pase a datos_cliente_nuevo
            $estado->cliente_existe_erp = false;
        }

        $estado->marcarValidacion('cliente_erp', true);
        $estado->save();
        $this->avanzarPaso($estado);

        return $estado;
    }

    /**
     * Marca el pedido como confirmado (cuando guardarPedidoDesdeToolCall tuvo éxito).
     */
    public function marcarConfirmado(ConversacionWhatsapp $conv, int $pedidoId): void
    {
        $estado = $this->obtener($conv);
        $estado->paso_actual   = ConversacionPedidoEstado::PASO_CONFIRMADO;
        $estado->pedido_id     = $pedidoId;
        $estado->confirmado_at = now();
        $estado->save();
    }

    /**
     * 🔍 Captura PROACTIVA de datos del mensaje del usuario.
     * Detecta y persiste cédula, email, etc. ANTES de que el bot procese.
     * Así no se pierden datos aunque el LLM no llame la tool correcta.
     */
    /**
     * 🛡️ Setea la cédula en el estado. Si la cédula nueva es DISTINTA a la
     * actual (probablemente venía de hidratación del titular), resetea nombre
     * y email del estado para que el bot los pida al cliente correcto. Esto
     * evita registrar pedidos a nombre de otra persona cuando alguien usa el
     * celular de un familiar/amigo.
     */
    private function setCedulaConResetSiCambia(ConversacionPedidoEstado $estado, string $nuevaCedula, ConversacionWhatsapp $conv): void
    {
        $cedulaAnterior = trim((string) $estado->cedula);
        if ($cedulaAnterior !== '' && $cedulaAnterior !== $nuevaCedula) {
            // El cliente da una cédula DISTINTA a la guardada — es otra persona.
            // Limpiar nombre y email para que el bot los pida.
            Log::warning('🛡️ Cédula cambió — reseteando nombre/email para evitar identidad cruzada', [
                'conv_id'   => $conv->id,
                'anterior'  => $cedulaAnterior,
                'nueva'     => $nuevaCedula,
                'nombre_anterior' => $estado->nombre_cliente,
                'email_anterior'  => $estado->email,
            ]);
            $estado->nombre_cliente = null;
            $estado->email          = null;
        }
        $estado->cedula = $nuevaCedula;
    }

    public function captarDelMensajeUsuario(ConversacionWhatsapp $conv, string $mensaje): void
    {
        $estado = $this->obtener($conv);
        $msg = trim($mensaje);
        $cambio = false;

        // 1. CÉDULA — número de 6-12 dígitos CONTIGUOS, no en dirección/pago.
        //    Ej válidos: "1098765432", "Mi cédula es 1234567"
        //    Ej INVÁLIDOS: "Cra 50 # 51-00" (dígitos separados), "transferencia 3216499744"
        if (empty($estado->cedula)) {
            $msgLower = mb_strtolower($msg);
            $contextoPago = preg_match('/(transferencia|nequi|daviplata|bancolombia|pse|cuenta|consign|tarjeta|cel(ular)?|tel[eé]fono|whatsapp)/iu', $msgLower) === 1;
            // 🛡️ NUEVO: detectar si el mensaje contiene palabras de DIRECCIÓN
            $contextoDireccion = preg_match('/\b(calle|cra|carrera|cl|cll|cr|kr|av|avenida|diag|diagonal|trv|transversal|via|autopista|circular|barrio|apto|apartamento|torre|bloque|casa|piso)\b/iu', $msgLower) === 1;
            $telefonoCliente = preg_replace('/[^\d]/', '', (string) ($conv->telefono_normalizado ?? ''));

            $esPosibleTelefono = function (string $clean) use ($telefonoCliente): bool {
                if ($telefonoCliente !== '' && (
                    $clean === $telefonoCliente
                    || str_ends_with($telefonoCliente, $clean)
                    || str_ends_with($clean, $telefonoCliente)
                )) return true;
                if (preg_match('/^3\d{9}$/', $clean)) return true;
                if (preg_match('/^573\d{9}$/', $clean)) return true;
                return false;
            };

            // 🛡️ Caso A: el mensaje ES SOLO la cédula (puede tener puntos pero no espacios entre dígitos)
            //    "1098765432" → ✅
            //    "1.098.765.432" → ✅
            //    "Cra 50 # 51-00" → ❌ (dígitos en grupos separados)
            $msgTrim = trim($msg);
            $soloDigitosYPuntos = preg_match('/^[\d.,]+$/', $msgTrim) === 1;
            $clean = preg_replace('/[^\d]/', '', $msgTrim);

            if ($soloDigitosYPuntos
                && !$contextoPago
                && !$contextoDireccion
                && preg_match('/^\d{6,12}$/', $clean)
                && !$esPosibleTelefono($clean)
            ) {
                $this->setCedulaConResetSiCambia($estado, $clean, $conv);
                $cambio = true;
                Log::info('🔍 Cédula capturada del mensaje', ['conv_id' => $conv->id, 'cedula' => $clean]);
            }
            // 🛡️ Caso B: prefijo explícito ("mi cédula es 1234567") — siempre confiable
            elseif (preg_match('/\b(?:c[eé]dula|cc|documento|nit|ced)[\s:]*([\d.,]{6,15})\b/iu', $msg, $m)) {
                $cleanB = preg_replace('/[^\d]/', '', $m[1]);
                if (preg_match('/^\d{6,12}$/', $cleanB) && !$esPosibleTelefono($cleanB)) {
                    $this->setCedulaConResetSiCambia($estado, $cleanB, $conv);
                    $cambio = true;
                    Log::info('🔍 Cédula capturada con prefijo', ['conv_id' => $conv->id, 'cedula' => $cleanB]);
                }
            }
            // 🛡️ Caso C: dígitos CONTIGUOS de 7-12 chars en mensaje SIN contexto de dirección/pago
            //    "Sí, mi cédula 1098765432 listo" → captura 1098765432
            //    "Cra 50 # 51-00" → NO captura (contexto dirección)
            elseif (!$contextoPago && !$contextoDireccion
                && preg_match('/\b(\d{7,12})\b/', $msg, $m)) {
                $cleanC = $m[1];
                if (!$esPosibleTelefono($cleanC)) {
                    $this->setCedulaConResetSiCambia($estado, $cleanC, $conv);
                    $cambio = true;
                    Log::info('🔍 Cédula capturada (dígitos contiguos)', ['conv_id' => $conv->id, 'cedula' => $cleanC]);
                }
            }
            // Caso INTERNO: si la cédula que llega es distinta a la actual del estado
            // (que pudo venir de hidratación), aplicar lógica de "pedido para otro".
            // Esto se ejecuta ANTES del else.
            else {
                if ($contextoPago && preg_match('/^\d{6,12}$/', $clean)) {
                    Log::info('🛡️ Número ignorado como cédula — contexto de pago/teléfono', [
                        'conv_id' => $conv->id,
                        'numero'  => $clean,
                        'mensaje' => mb_substr($msg, 0, 80),
                    ]);
                } elseif ($clean !== '' && $esPosibleTelefono($clean)) {
                    Log::info('🛡️ Número ignorado como cédula — parece teléfono', [
                        'conv_id' => $conv->id,
                        'numero'  => $clean,
                    ]);
                }
            }
        }

        // 2. EMAIL — captura o actualiza si llega uno nuevo válido.
        //    Si el cliente da un email distinto al guardado, prevalece el nuevo.
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $msg, $m)) {
            $nuevoEmail = mb_strtolower($m[0]);
            if (filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL) && $estado->email !== $nuevoEmail) {
                $emailAnterior  = $estado->email;
                $estado->email  = $nuevoEmail;
                $cambio = true;
                Log::info('🔍 Email capturado/actualizado', [
                    'conv_id'   => $conv->id,
                    'email'     => $nuevoEmail,
                    'anterior'  => $emailAnterior,
                ]);
            }
        }

        // 3. SEDE — si el cliente está en flujo de recoger y aún no tiene sede
        if (
            empty($estado->sede_id) &&
            $estado->metodo_entrega === ConversacionPedidoEstado::METODO_RECOGER
        ) {
            $sedes = \App\Models\Sede::where('activa', true)->orderBy('id')->get();

            // 3a. Si el mensaje es solo un número (1, 2, 3...) → posición en lista
            if (preg_match('/^\s*(\d{1,2})\s*\.?\s*$/', $msg, $m)) {
                $idx = (int) $m[1] - 1;
                if (isset($sedes[$idx])) {
                    $estado->sede_id = $sedes[$idx]->id;
                    $cambio = true;
                    Log::info('🔍 Sede capturada por posición en lista', [
                        'conv_id' => $conv->id,
                        'opcion'  => $m[1],
                        'sede_id' => $estado->sede_id,
                        'nombre'  => $sedes[$idx]->nombre,
                    ]);
                }
            }

            // 3b. Si el mensaje contiene el nombre de una sede → match
            if (!$cambio || empty($estado->sede_id)) {
                $msgLower = mb_strtolower($msg);
                foreach ($sedes as $s) {
                    if (str_contains($msgLower, mb_strtolower($s->nombre))) {
                        $estado->sede_id = $s->id;
                        $cambio = true;
                        Log::info('🔍 Sede capturada por nombre', [
                            'conv_id' => $conv->id,
                            'sede_id' => $s->id,
                            'nombre'  => $s->nombre,
                        ]);
                        break;
                    }
                }
            }
        }

        // 3.5. DIRECCIÓN — patrones colombianos comunes
        //      "cra 58bb #40c-34", "calle 50 #47-80", "carrera 80a #45-12",
        //      "diagonal 67 # 32-15", "cl 79 sur #52-84", "trv 40 #30-20"
        // 🛡️ Captura nueva o REEMPLAZA si la anterior fue rechazada por
        // cobertura (cliente da otra dirección alternativa).
        $puedeCapturarDireccion = empty($estado->direccion)
            || (!empty($estado->direccion) && !$estado->cobertura_validada);

        if ($puedeCapturarDireccion) {
            $direccionCapturada = $this->detectarDireccionEnMensaje($msg);
            if ($direccionCapturada && $direccionCapturada !== $estado->direccion) {
                $direccionAnterior = $estado->direccion;
                $estado->direccion = $direccionCapturada;
                // Reset cobertura para forzar re-validación con la NUEVA dirección
                $estado->cobertura_validada = false;
                $estado->distancia_km = null;
                $estado->costo_envio = null;
                if (empty($estado->metodo_entrega)) {
                    $estado->metodo_entrega = ConversacionPedidoEstado::METODO_DOMICILIO;
                }
                $cambio = true;
                Log::info('🔍 Dirección capturada del mensaje', [
                    'conv_id'            => $conv->id,
                    'direccion'          => $estado->direccion,
                    'direccion_anterior' => $direccionAnterior,
                ]);
            }
        }

        // 4. MÉTODO DE ENTREGA — despacho / recoger
        // (internamente METODO_DOMICILIO sigue siendo 'domicilio' para no
        // romper datos existentes, pero la UX habla de 'despacho')
        if (empty($estado->metodo_entrega)) {
            $msgLowerMe = mb_strtolower($msg);
            // Patrones DESPACHO (incluye 'domicilio' por si el cliente usa esa palabra)
            if (preg_match('/\b(despach[oa]?|domicilio|env[ií]o|env[ií]eme|env[ií]o\s+a|me\s+lo\s+env|me\s+lo\s+mand|m[áa]ndamelo|para\s+casa|a\s+mi\s+casa|env[ií]a|env[ií]ar)\b/iu', $msgLowerMe)) {
                $estado->metodo_entrega = ConversacionPedidoEstado::METODO_DOMICILIO;
                $cambio = true;
                Log::info('🔍 Método entrega DESPACHO capturado', ['conv_id' => $conv->id]);
            }
            // Patrones RECOGER (cliente recoge) — incluye sinónimos cortos: sede, tienda, punto, almacen, paso, voy, recojo
            elseif (preg_match('/\b(recog[eo]|recoger|recojo|recogerlo|reclamo|reclamar|paso\s+a\s+recoger|paso\s+por|paso|voy\s+a\s+pasar|voy|yo\s+voy|en\s+la\s+sede|en\s+sede|sede|tienda|punto|almac[eé]n|local|cliente\s+recoge|yo\s+recojo)\b/iu', $msgLowerMe)) {
                $estado->metodo_entrega = ConversacionPedidoEstado::METODO_RECOGER;
                $cambio = true;
                Log::info('🔍 Método entrega RECOGER capturado', ['conv_id' => $conv->id]);
            }
        }

        // 4.5. PRODUCTOS — captura robusta + ACUMULA productos nuevos.
        // Si el cliente pide productos en mensajes separados, los AGREGAMOS
        // a la lista en lugar de reemplazarla.
        //   Mensaje 1: "3 libras de chicharrón" → [chicharrón]
        //   Mensaje 2: "y 1 kilo de pollo"      → [chicharrón, pollo]
        //   Mensaje 3: "y 2 kg de res"          → [chicharrón, pollo, res]
        $productosCapturados = $this->extraerProductosDelMensaje($msg);
        if (!empty($productosCapturados)) {
            $existentes = $estado->productos ?? [];
            $codigosExistentes = collect($existentes)
                ->pluck('code')
                ->filter()
                ->all();

            $nuevosAgregados = [];
            foreach ($productosCapturados as $nuevo) {
                $codigoNuevo = $nuevo['code'] ?? '';
                if ($codigoNuevo === '' || !in_array($codigoNuevo, $codigosExistentes, true)) {
                    $existentes[] = $nuevo;
                    $nuevosAgregados[] = $nuevo;
                }
            }

            if (!empty($nuevosAgregados)) {
                $estado->productos = $existentes;
                $cambio = true;
                Log::info('🔍 Productos capturados del mensaje', [
                    'conv_id'         => $conv->id,
                    'nuevos'          => $nuevosAgregados,
                    'total_productos' => count($existentes),
                ]);
            }
        }

        // 5. NOMBRE — heurística que detecta nombres incluso mixtos.
        //    Sobrescribe si el actual NO parece un nombre real (emoji, vacío,
        //    'Cliente', email, número solo).
        $nombreActualValido = !empty($estado->nombre_cliente)
            && !str_contains((string) $estado->nombre_cliente, '@')
            && preg_match('/[a-záéíóúñ]/iu', (string) $estado->nombre_cliente)
            && preg_match('/^[a-záéíóúñA-ZÁÉÍÓÚÑ\s]+$/', (string) $estado->nombre_cliente)
            && strtolower((string) $estado->nombre_cliente) !== 'cliente';

        if (!$nombreActualValido) {
            $nombre = $this->extraerNombreDeMensaje($msg);
            if ($nombre) {
                $anterior = $estado->nombre_cliente;
                $estado->nombre_cliente = $nombre;
                $cambio = true;
                Log::info('🔍 Nombre capturado del mensaje', [
                    'conv_id'  => $conv->id,
                    'nombre'   => $nombre,
                    'anterior' => $anterior,
                ]);
            }
        }

        if ($cambio) {
            $estado->save();
            $this->avanzarPaso($estado);
        }
    }

    /**
     * 🛡️ Extrae un nombre persona del mensaje, ignorando cédulas, emails,
     * teléfonos y frases funcionales que pueda contener.
     *
     * Estrategia:
     *   1. Limpiar números, @, símbolos.
     *   2. Detectar secuencias de 2-5 palabras alfabéticas seguidas.
     *   3. Filtrar frases funcionales (hola, quiero, gracias, etc).
     *   4. Devolver la secuencia más larga válida.
     */
    private function extraerNombreDeMensaje(string $msg): ?string
    {
        $msg = trim($msg);
        if ($msg === '') return null;

        // Quitar emails, URLs, números y símbolos no-letra
        $limpio = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', ' ', $msg);
        $limpio = preg_replace('/https?:\/\/\S+/i', ' ', $limpio);
        $limpio = preg_replace('/\d+/', ' ', $limpio);
        $limpio = preg_replace('/[^a-záéíóúñÁÉÍÓÚÑ\s]/iu', ' ', $limpio);
        $limpio = preg_replace('/\s+/u', ' ', trim($limpio));

        if ($limpio === '') return null;

        // Frases funcionales a descartar
        $stopwords = '/^(hola|buenas|buenos\s+dias|tardes|noches|gracias|si|no|listo|dale|quiero|necesito|tienes|tienen|para|de|del|los|las|el|la|por\s+favor|domicilio|despacho|recoger|aqui|alla|que|qué|cómo|como|cuanto|cuánto|cual|cuál)$/iu';

        $palabras = explode(' ', $limpio);
        $palabras = array_filter($palabras, fn ($p) => mb_strlen($p) >= 2);
        $palabras = array_values($palabras);

        // Buscar la secuencia más larga de palabras alfabéticas válidas (2-5)
        $mejorNombre = null;
        $mejorScore = 0;
        $secuenciaActual = [];

        foreach ($palabras as $p) {
            $esFuncional = (bool) preg_match($stopwords, mb_strtolower($p));
            if ($esFuncional) {
                // Cerrar secuencia y evaluarla
                if (count($secuenciaActual) >= 2 && count($secuenciaActual) <= 5) {
                    $candidato = implode(' ', $secuenciaActual);
                    if (count($secuenciaActual) > $mejorScore) {
                        $mejorScore = count($secuenciaActual);
                        $mejorNombre = $candidato;
                    }
                }
                $secuenciaActual = [];
            } else {
                $secuenciaActual[] = $p;
            }
        }
        // Evaluar secuencia final
        if (count($secuenciaActual) >= 2 && count($secuenciaActual) <= 5) {
            if (count($secuenciaActual) > $mejorScore) {
                $mejorNombre = implode(' ', $secuenciaActual);
            }
        }

        if (!$mejorNombre) return null;

        // Capitalizar correctamente
        return mb_convert_case(trim($mejorNombre), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * 🛡️ Detector ULTRA-PERMISIVO de direcciones colombianas.
     *
     * Estrategia en 2 niveles:
     *   1. Busca palabras clave de vía (calle, carrera, cra, av, etc.)
     *      + al menos un número en el mensaje.
     *   2. Si encuentra → captura desde la palabra clave hasta el final
     *      del mensaje o un delimitador fuerte (salto de línea, "."
     *      seguido de mayúscula).
     *
     * Acepta CASI cualquier formato razonable.
     */
    private function detectarDireccionEnMensaje(string $msg): ?string
    {
        // 🛡️ Pre-limpieza: si el cliente metió cédula/email en el mismo mensaje,
        // los quitamos del texto ANTES de capturar la dirección. Antes la dirección
        // quedaba sucia: "Cra 50 No. 51 00 Bello, mi cedula es 1007767612"
        $msgLimpio = $msg;
        // Quitar email
        $msgLimpio = preg_replace('/\s*[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}\s*/iu', ' ', $msgLimpio);
        // Quitar segmentos con "mi cedula es 12345", "cedula 12345", "cc 12345", "documento 12345"
        $msgLimpio = preg_replace('/[,;]?\s*(?:mi\s+)?(?:c[eé]dula|cc|cédula|documento|nit|ced)[\s:]*\d{6,15}\.?/iu', '', $msgLimpio);
        // Quitar mención sin prefijo de un número largo aislado (8+ dígitos) al final del mensaje
        $msgLimpio = preg_replace('/[,;]?\s*\d{8,12}\s*\.?\s*$/u', '', $msgLimpio);

        // Palabras clave de vía colombianas (con/sin punto, abreviadas o no)
        $palabrasVia = '(?:cra\.?|carrera|cl\.?|calle|cll|dg\.?|diagonal|diag|trv\.?|transversal|av\.?|avenida|cr|kr|circular|autopista|via|tv)';

        // Patrón principal: palabra de vía + cualquier cosa con al menos un número
        // hasta el final del mensaje o delimitador fuerte
        $patron = '/\b' . $palabrasVia . '\s*\.?\s*\d[\w\s#\-\/\.,°áéíóúñ]*/iu';
        if (preg_match($patron, $msgLimpio, $m)) {
            $dir = trim($m[0]);
            // Limpiar trailing common: comas, puntos sueltos al final
            $dir = preg_replace('/[\s,.;]+$/u', '', $dir);
            // Validar longitud mínima razonable
            if (mb_strlen($dir) >= 6) {
                return $dir;
            }
        }

        // Fallback: si el mensaje es claramente solo dirección sin palabra de vía
        // (ej. "#45-23, Belén") + al menos 2 números separados
        if (preg_match('/^#?\s*\d+[a-z]?\s*[-#\s\/]\s*\d+/iu', trim($msgLimpio), $m)) {
            return trim($msgLimpio);
        }

        return null;
    }

    /**
     * 🛡️ Extrae lista de productos del mensaje del cliente, validando contra
     * el catálogo activo del tenant. Solo retorna productos que existen.
     *
     * Cada producto: ['name' => str, 'quantity' => float, 'unit' => str, 'code' => str]
     */
    private function extraerProductosDelMensaje(string $msg): array
    {
        $productos = [];
        $msgN = mb_strtolower(\Illuminate\Support\Str::ascii(trim($msg)));
        if ($msgN === '') return [];

        // 🛡️ Limpiar caracteres de formato markdown que confunden al regex
        // (asterisks, guion bajo, comillas que algunos clientes usan).
        $msgN = preg_replace('/[\*_"`]/u', ' ', $msgN);
        $msgN = preg_replace('/\s+/u', ' ', trim($msgN));

        // 🛡️ Normalizar palabras de cantidad escritas a dígitos.
        // "una pierna de cerdo" → "1 pierna de cerdo"
        // "media libra"          → "0.5 libra"
        $msgN = strtr($msgN, [
            'una '  => '1 ',
            'un '   => '1 ',
            'dos '  => '2 ',
            'tres ' => '3 ',
            'cuatro ' => '4 ',
            'cinco ' => '5 ',
            'media ' => '0.5 ',
            'medio ' => '0.5 ',
        ]);

        // Cargar catálogo (tokens de nombres reales) para validar matches
        try {
            $catalogo = app(\App\Services\BotCatalogoService::class)->productosActivos();
        } catch (\Throwable $e) {
            $catalogo = collect();
        }
        if ($catalogo->isEmpty()) return [];

        // Mapa nombre normalizado → producto (con tokens significativos)
        $catalogoTokens = [];
        foreach ($catalogo as $p) {
            $nombreNorm = mb_strtolower(\Illuminate\Support\Str::ascii((string) $p->nombre));
            $tokens = collect(preg_split('/\s+/', $nombreNorm))
                ->filter(fn ($t) => mb_strlen($t) >= 4)
                ->values()->all();
            if (!empty($tokens)) {
                $catalogoTokens[] = [
                    'producto'  => $p,
                    'tokens'    => $tokens,
                    'nombre'    => $nombreNorm,
                    'codigo'    => (string) ($p->codigo ?? ''),
                    'unidad'    => (string) ($p->unidad ?? 'Und'),
                ];
            }
        }

        // Patrón base: cantidad + unidad + (de )? + nombre
        // Ej: "2 kilos de trucha", "1 libra pierna", "kilo de pollo"
        $patronCantidad = '/(?:^|[^\d])(\d+(?:[.,]\d+)?)\s*(libras?|libritas?|lb|kilos?|kg|kilitos?|gramos?|gr|unidades?|und?s?|porciones?|cajas?|paquetes?|pack|bolsas?)\s*(?:de\s+)?([a-záéíóúñ\s]+?)(?=\s+(?:y|,|\.|$)|\s+\d|$)/iu';
        $patronCantidadImplicita = '/(?:^|\s)(libra|kilo|kilito|kg|lb)\s+(?:de\s+)?([a-záéíóúñ\s]+?)(?=\s+(?:y|,|\.|$)|$)/iu';

        if (preg_match_all($patronCantidad, $msgN, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $cantidad = (float) str_replace(',', '.', $m[1]);
                $unidadRaw = mb_strtolower(rtrim($m[2], 's'));
                $nombreCandidato = trim($m[3]);
                $producto = $this->matchProductoEnCatalogo($nombreCandidato, $catalogoTokens);
                if ($producto) {
                    $productos[] = [
                        'code'     => $producto['codigo'],
                        'name'     => (string) $producto['producto']->nombre,
                        'quantity' => $cantidad,
                        'unit'     => $producto['unidad'],
                    ];
                }
            }
        }

        // Si no hubo cantidad explícita, intentar cantidad implícita = 1
        if (empty($productos) && preg_match_all($patronCantidadImplicita, $msgN, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $m) {
                $nombreCandidato = trim($m[2]);
                $producto = $this->matchProductoEnCatalogo($nombreCandidato, $catalogoTokens);
                if ($producto) {
                    $productos[] = [
                        'code'     => $producto['codigo'],
                        'name'     => (string) $producto['producto']->nombre,
                        'quantity' => 1.0,
                        'unit'     => $producto['unidad'],
                    ];
                }
            }
        }

        // 🛡️ FALLBACK B: "PRODUCTO + cantidad + unidad" (orden inverso)
        //   "TILAPIA 1 kg"
        //   "TILAPIA — 1 kg — $20.000"
        //   "PIERNA DE CERDO 2 kilos"
        if (empty($productos)) {
            $patronProdCant = '/([a-záéíóúñ][a-záéíóúñ\s]+?)[\s\-—,]+(\d+(?:[.,]\d+)?)\s*(libras?|libritas?|lb|kilos?|kg|kilitos?|gramos?|gr|unidades?|unds?|porciones?|cajas?|paquetes?)/iu';
            if (preg_match_all($patronProdCant, $msgN, $m4, PREG_SET_ORDER)) {
                foreach ($m4 as $m) {
                    $nombreCandidato = trim($m[1]);
                    $cantidad = (float) str_replace(',', '.', $m[2]);
                    $unidadRaw = mb_strtolower(rtrim($m[3], 's'));
                    $producto = $this->matchProductoEnCatalogo($nombreCandidato, $catalogoTokens);
                    if ($producto) {
                        $productos[] = [
                            'code'     => $producto['codigo'],
                            'name'     => (string) $producto['producto']->nombre,
                            'quantity' => $cantidad,
                            'unit'     => $producto['unidad'],
                        ];
                    }
                }
            }
        }

        // 🛡️ FALLBACK: "N producto" sin unidad explícita
        // "1 pierna de cerdo", "2 muslos", "1 filete tilapia"
        // Solo busca cuando NO se ha capturado nada antes.
        if (empty($productos)) {
            $patronNumProd = '/(?:^|[^\d])(\d+(?:[.,]\d+)?)\s+([a-záéíóúñ][a-záéíóúñ\s]+?)(?=[,.\n]|$)/iu';
            if (preg_match_all($patronNumProd, $msgN, $m3, PREG_SET_ORDER)) {
                foreach ($m3 as $m) {
                    $cantidad = (float) str_replace(',', '.', $m[1]);
                    $nombreCandidato = trim($m[2]);
                    // Limpiar palabras de cierre típicas
                    $nombreCandidato = preg_replace('/^(de\s+|del\s+)/iu', '', $nombreCandidato);
                    $producto = $this->matchProductoEnCatalogo($nombreCandidato, $catalogoTokens);
                    if ($producto) {
                        $productos[] = [
                            'code'     => $producto['codigo'],
                            'name'     => (string) $producto['producto']->nombre,
                            'quantity' => $cantidad,
                            'unit'     => $producto['unidad'],
                        ];
                    }
                }
            }
        }

        // 🛡️ ÚLTIMO FALLBACK: solo nombre de producto sin cantidad
        // "pierna de cerdo" sola → cantidad implícita 1
        if (empty($productos)) {
            $producto = $this->matchProductoEnCatalogo($msgN, $catalogoTokens);
            if ($producto) {
                $productos[] = [
                    'code'     => $producto['codigo'],
                    'name'     => (string) $producto['producto']->nombre,
                    'quantity' => 1.0,
                    'unit'     => $producto['unidad'],
                ];
            }
        }

        // Deduplicar por código
        $unique = [];
        foreach ($productos as $p) {
            $unique[$p['code'] ?: $p['name']] = $p;
        }
        return array_values($unique);
    }

    /**
     * Encuentra el producto del catálogo que mejor matchea el nombre dado.
     *
     * 🛡️ ESTRICTO + ANTI-AMBIGÜEDAD:
     *   1. Si hay match EXACTO de nombre normalizado → ese producto.
     *   2. Si hay UN ÚNICO producto donde TODOS los tokens del cliente
     *      coinciden Y el producto NO tiene tokens "extras" inventados
     *      (cliente "tilapia" matchea "TILAPIA" pero NO "FILETE TILAPIA")
     *      → ese producto.
     *   3. Si hay ambigüedad (>1 candidato) → null. El LLM se encarga.
     */
    private function matchProductoEnCatalogo(string $nombreCandidato, array $catalogoTokens): ?array
    {
        $cn = mb_strtolower(\Illuminate\Support\Str::ascii(trim($nombreCandidato)));
        if (mb_strlen($cn) < 3) return null;

        // 🛡️ Términos demasiado genéricos: si el cliente dice solo
        // "res", "cerdo", "pollo", "pescado" (categorías), NO matchear.
        $genericos = ['res', 'cerdo', 'pollo', 'pescado', 'carne', 'pez'];
        if (in_array($cn, $genericos, true)) return null;

        // Tokens significativos del cliente (≥4 chars)
        $tokensCliente = collect(preg_split('/\s+/', $cn))
            ->filter(fn ($t) => mb_strlen($t) >= 4)
            ->values()->all();
        if (empty($tokensCliente)) return null;

        // 1) Match exacto de nombre completo
        foreach ($catalogoTokens as $entry) {
            if ($entry['nombre'] === $cn) return $entry;
        }

        // 2) Buscar candidatos donde TODOS los tokens del cliente aparezcan
        $candidatos = [];
        foreach ($catalogoTokens as $entry) {
            $todosCoinciden = true;
            foreach ($tokensCliente as $tc) {
                $coincide = false;
                foreach ($entry['tokens'] as $te) {
                    if ($tc === $te) { $coincide = true; break; }
                    if (mb_strlen($tc) >= 5 && (str_contains($te, $tc) || str_contains($tc, $te))) {
                        $coincide = true;
                        break;
                    }
                }
                if (!$coincide) { $todosCoinciden = false; break; }
            }
            if ($todosCoinciden) $candidatos[] = $entry;
        }

        if (empty($candidatos)) return null;

        // 3) De los candidatos, preferir el que tenga el MISMO número de
        // tokens significativos (mismo nivel de especificidad).
        $tokensClienteCount = count($tokensCliente);
        $coincidenciasExactas = array_filter(
            $candidatos,
            fn ($e) => count($e['tokens']) === $tokensClienteCount
        );

        // Si hay UN solo match con misma especificidad → ese
        if (count($coincidenciasExactas) === 1) {
            return array_values($coincidenciasExactas)[0];
        }

        // 4) Si hay AMBIGÜEDAD (varios candidatos) → no auto-asignar.
        //    El LLM debe llamar buscar_productos y mostrar opciones al cliente.
        if (count($candidatos) > 1) {
            \Illuminate\Support\Facades\Log::info('🛡️ Captador: ambigüedad detectada — pasando al LLM', [
                'cliente_dijo'  => $nombreCandidato,
                'candidatos'    => array_map(fn ($e) => $e['producto']->nombre ?? '?', $candidatos),
            ]);
            return null;
        }

        // 5) Si solo hay UN candidato (con todos los tokens del cliente) → ese
        return $candidatos[0];
    }

    /**
     * Detecta si el cliente está intentando iniciar un NUEVO pedido (después de
     * uno ya confirmado). Frases que indican esto:
     *   "quiero otro pedido", "agrégame otro", "para otro pedido", "uno más",
     *   "ahora me das…", o si menciona un producto sin estar pidiendo seguimiento.
     *
     * NO se considera nuevo pedido si solo pregunta por el anterior:
     *   "¿cuándo llega?", "¿ya salió?", "estado del pedido", etc.
     */
    public function detectarIntencionNuevoPedido(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        if ($m === '') return false;

        // 1) Patrones EXPLÍCITOS de nuevo pedido
        $intencionNueva = [
            // "otro pedido"
            'otro pedido',
            'nuevo pedido',
            'un pedido más',
            'un pedido mas',
            'pedir de nuevo',
            'volver a pedir',
            'sumar al pedido',
            // "para un pedido" / "para pedir"
            'para un pedido',
            'para pedir',
            'para hacer un pedido',
            'quisiera un pedido',
            'voy a pedir',
            'me gustaría pedir',
            'me gustaria pedir',
            // "otras cosas" / "más cosas"
            'otras cosas',
            'otra cosa',
            'mas cosas',
            'más cosas',
            'cosas más',
            'cosas mas',
            'pedir cosas',
            'pedir otra',
            'pedir otro',
            'pedir más',
            'pedir mas',
            'pedir algo',
            'algo más',
            'algo mas',
            // "agrégame X"
            'agrégame',
            'agregame',
            'agréguenme',
            'agreguenme',
            'añádeme',
            'añademe',
            'añadir',
            'anadir',
            // "quiero más / también"
            'quiero más',
            'quiero mas',
            'quiero otro',
            'quiero otra',
            'quiero pedir',
            'también quiero',
            'tambien quiero',
            'también un',
            'tambien un',
            'también una',
            'tambien una',
            'me das también',
            'me das tambien',
            'aparte quiero',
            'aparte un',
            'aparte una',
            // verbos directos
            'hacer otro',
            'hacer un pedido',
            'comprar otra',
            'comprar otro',
            'comprar mas',
            'comprar más',
        ];

        foreach ($intencionNueva as $p) {
            if (str_contains($m, $p)) return true;
        }

        // 2) Patrones que indican SEGUIMIENTO del pedido anterior — NO es nuevo
        $seguimiento = [
            'cuándo llega',
            'cuando llega',
            'ya salió',
            'ya salio',
            'dónde está',
            'donde esta',
            'estado del pedido',
            'cancelar',
            'cancela el',
            'modificar el pedido',
            'cambiar el pedido',
            '¿llegó?',
            'llegó?',
            'llego?',
            'recibí',
            'recibi',
            'todo bien',
        ];
        foreach ($seguimiento as $s) {
            if (str_contains($m, $s)) return false;
        }

        // 3) HEURÍSTICA: si menciona cantidad + unidad común de producto, probablemente es nuevo pedido
        //    Tolera typos comunes: libraa/libritas/kilitos/kilooo/etc
        //    Ej: "5 libras de solomo", "4 libraa de pierna", "1 kg pierna"
        $unidadesComunes = '(libra+s?|kilo+s?|kilito+s?|kg+|gramo+s?|gr+|unidade+s?|unidad+|caja+s?|paquete+s?|bolsa+s?|docena+s?|gallina+s?|porci[oó]n+s?|botella+s?|lata+s?)';
        if (preg_match('/\b\d+\s*' . $unidadesComunes . '\b/iu', $m)) {
            return true;
        }
        // Variantes con palabras: "una libra", "un kilo", "media libra"
        if (preg_match('/\b(una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|media|medio)\s*' . $unidadesComunes . '\b/iu', $m)) {
            return true;
        }

        return false;
    }

    /**
     * Resetea el estado (al saludar tras inactividad, o tras confirmar pedido nuevo).
     *
     * IMPORTANTE: para clientes recurrentes (ya tenemos cédula + nombre + ERP),
     * preservamos esos datos para que NO tenga que volver a darlos en el
     * pedido nuevo. Solo limpiamos lo específico del pedido (productos, sede,
     * dirección, etc.).
     */
    public function resetear(ConversacionWhatsapp $conv, ?string $motivo = null): void
    {
        $estado = $this->obtener($conv);

        // 🎯 ¿Es un nuevo pedido del mismo cliente ya identificado?
        $esNuevoPedidoConClienteIdentificado = (
            $motivo
            && str_starts_with((string) $motivo, 'nuevo_pedido_tras_')
            && $estado->cedula
            && $estado->cliente_existe_erp
        );

        $cedulaPreservar  = $esNuevoPedidoConClienteIdentificado ? $estado->cedula : null;
        $nombrePreservar  = $esNuevoPedidoConClienteIdentificado ? $estado->nombre_cliente : null;
        $erpExistePreserv = $esNuevoPedidoConClienteIdentificado ? true : false;
        $datosErpPreserv  = $esNuevoPedidoConClienteIdentificado ? $estado->datos_erp : null;
        $telefonoPreserv  = $estado->telefono ?: $conv->telefono_normalizado;

        $estado->fill([
            'paso_actual'        => ConversacionPedidoEstado::PASO_INICIO,
            'productos'          => null,
            'metodo_entrega'     => null,
            'sede_id'            => null,
            'direccion'          => null,
            'barrio'             => null,
            'ciudad'             => null,
            'cobertura_validada' => false,
            'distancia_km'       => null,
            'costo_envio'        => null,
            'cedula'             => $cedulaPreservar,
            'nombre_cliente'     => $nombrePreservar,
            'telefono'           => $telefonoPreserv,
            'email'              => null,
            'cliente_existe_erp' => $erpExistePreserv,
            'datos_erp'          => $datosErpPreserv,
            'metodo_pago'        => null,
            'cupon_code'         => null,
            'notas'              => null,
            'validaciones'       => $esNuevoPedidoConClienteIdentificado
                ? ['cliente_erp' => true]
                : null,
            'pedido_id'          => null,
            'confirmado_at'      => null,
            'abandonado_at'      => $motivo ? now() : null,
            'motivo_abandono'    => $motivo,
        ])->save();

        Log::info('🔄 Estado pedido reseteado', [
            'conv_id'             => $conv->id,
            'motivo'              => $motivo,
            'cliente_preservado'  => $esNuevoPedidoConClienteIdentificado,
        ]);
    }

    /**
     * Lógica simple de avance de paso basada en datos disponibles.
     * NO usa LLM. Es una máquina de estados determinista.
     */
    public function avanzarPaso(ConversacionPedidoEstado $estado): void
    {
        $nuevo = $estado->paso_actual;

        // ¿Hay integración ERP activa con cliente_lookup?
        $erpActivo  = $this->erpClienteLookupActivo();
        $camposReqErp = $this->camposRequeridosErp();

        if (empty($estado->productos)) {
            $nuevo = ConversacionPedidoEstado::PASO_PRODUCTO;
        } elseif (empty($estado->metodo_entrega) ||
                  ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_DOMICILIO && !$estado->cobertura_validada) ||
                  ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_RECOGER && empty($estado->sede_id))) {
            $nuevo = ConversacionPedidoEstado::PASO_ENTREGA;
        } elseif (empty($estado->cedula)) {
            // Aún no tenemos cédula → paso identificación
            $nuevo = ConversacionPedidoEstado::PASO_IDENTIFICACION;
        } elseif ($erpActivo && !$estado->yaValidado('cliente_erp')) {
            // Tenemos cédula pero NO hemos consultado ERP — sigue en identificación
            // hasta que verificar_cliente_erp se ejecute
            $nuevo = ConversacionPedidoEstado::PASO_IDENTIFICACION;
        } elseif ($erpActivo && !$estado->cliente_existe_erp && !$this->datosClienteCompletos($estado, $camposReqErp)) {
            // ERP consultado, NO existe, faltan datos → pedir datos del cliente nuevo
            $nuevo = ConversacionPedidoEstado::PASO_DATOS_CLIENTE;
        } elseif ($estado->estaCompleto() && !$estado->confirmado_at) {
            $nuevo = ConversacionPedidoEstado::PASO_CONFIRMACION;
        }

        if ($nuevo !== $estado->paso_actual && $estado->paso_actual !== ConversacionPedidoEstado::PASO_CONFIRMADO) {
            $estado->paso_actual = $nuevo;
            $estado->save();
        }
    }

    /**
     * ¿Tiene este tenant integración ERP con cliente_lookup activo?
     */
    private function erpClienteLookupActivo(): bool
    {
        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();
            if (!$tenantId) return false;
            return \App\Models\Integracion::where('tenant_id', $tenantId)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->contains(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lista de campos que SGI exige para crear un cliente nuevo.
     */
    public function camposRequeridosErp(): array
    {
        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();
            if (!$tenantId) return [];
            $integ = \App\Models\Integracion::where('tenant_id', $tenantId)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);
            return $integ?->config['cliente_lookup']['campos_requeridos'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * ¿Están todos los campos requeridos por ERP?
     */
    public function datosClienteCompletos(ConversacionPedidoEstado $estado, array $campos): bool
    {
        $mapa = [
            'cedula'    => $estado->cedula,
            'nombre'    => $estado->nombre_cliente,
            'telefono'  => $estado->telefono,
            'email'     => $estado->email,
            'direccion' => $estado->direccion,
        ];
        foreach ($campos as $campo) {
            if (empty($mapa[$campo] ?? null)) return false;
        }
        return true;
    }

    /**
     * Genera un resumen para inyectar en el prompt del bot — así el LLM
     * SIEMPRE sabe qué tiene recopilado, sin depender de leer el chat.
     */
    public function resumenParaPrompt(ConversacionWhatsapp $conv): string
    {
        $estado = $this->obtener($conv);

        if ($estado->paso_actual === ConversacionPedidoEstado::PASO_INICIO) {
            return '';
        }

        // 🛡️ Si el pedido YA fue confirmado, NO mostrarle al LLM los productos
        // y datos del pedido viejo. Solo decirle que ya hay uno cerrado.
        // Esto evita que el LLM "re-confirme" el pedido viejo cuando el cliente
        // saluda o pregunta cosas no relacionadas.
        if ($estado->paso_actual === ConversacionPedidoEstado::PASO_CONFIRMADO) {
            return "📋 ESTADO: el cliente {$estado->nombre_cliente} ya tiene un pedido confirmado "
                . "(#{$estado->pedido_id}). NO vuelvas a confirmar nada. Si quiere otro pedido, "
                . "pregúntale qué desea (el sistema reseteará el estado automáticamente). "
                . "Si solo saluda o pregunta por su pedido, responde cordial sin re-confirmar nada.";
        }

        $partes = ["📋 ESTADO ACTUAL DEL PEDIDO (BD — fuente de verdad):"];
        $partes[] = "  • Paso actual: {$estado->paso_actual}";

        if (!empty($estado->productos)) {
            $prods = collect($estado->productos)->map(fn ($p) =>
                ($p['quantity'] ?? 1) . ' ' . ($p['unit'] ?? '') . ' ' . ($p['name'] ?? '')
            )->implode(', ');
            $partes[] = "  • Productos: {$prods}";
        }

        if ($estado->metodo_entrega) {
            $entregaLabel = $estado->metodo_entrega === ConversacionPedidoEstado::METODO_DOMICILIO
                ? 'despacho'
                : 'cliente recoge';
            $partes[] = "  • Entrega: {$entregaLabel}";
            if ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_DOMICILIO && $estado->direccion) {
                $partes[] = "    Dirección: {$estado->direccion}" . ($estado->barrio ? ", {$estado->barrio}" : '');
                $partes[] = "    Cobertura validada: " . ($estado->cobertura_validada ? '✅' : '❌');
            }
            if ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_RECOGER && $estado->sede_id) {
                $partes[] = "    Sede: " . ($estado->sede?->nombre ?: "ID {$estado->sede_id}");
            }
        }

        if ($estado->cedula)         $partes[] = "  • Cédula: {$estado->cedula}" . ($estado->cliente_existe_erp ? ' (existe en ERP ✅)' : '');
        if ($estado->nombre_cliente) $partes[] = "  • Nombre: {$estado->nombre_cliente}";

        $faltantes = $estado->camposFaltantes();
        if (!empty($faltantes)) {
            $partes[] = "  ⚠️ Falta: " . implode(', ', $faltantes);
        } else {
            $partes[] = "  ✅ DATOS COMPLETOS — DEBES llamar confirmar_pedido AHORA.";
        }

        return implode("\n", $partes);
    }
}
