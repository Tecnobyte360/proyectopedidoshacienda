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
    public function obtener(ConversacionWhatsapp $conv): ConversacionPedidoEstado
    {
        return ConversacionPedidoEstado::firstOrCreate(
            ['conversacion_id' => $conv->id],
            [
                'tenant_id'   => $conv->tenant_id,
                'paso_actual' => ConversacionPedidoEstado::PASO_INICIO,
                'telefono'    => $conv->telefono_normalizado,
            ]
        );
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
    public function captarDelMensajeUsuario(ConversacionWhatsapp $conv, string $mensaje): void
    {
        $estado = $this->obtener($conv);
        $msg = trim($mensaje);
        $cambio = false;

        // 1. CÉDULA — número de 6-12 dígitos, posiblemente con puntos
        //    Ej: "1098765432", "1.098.765.432", "Mi cédula es 1234567"
        if (empty($estado->cedula)) {
            $clean = preg_replace('/[^\d]/', '', $msg);
            if (preg_match('/^\d{6,12}$/', $clean) && mb_strlen($msg) <= 25) {
                $estado->cedula = $clean;
                $cambio = true;
                Log::info('🔍 Cédula capturada del mensaje', ['conv_id' => $conv->id, 'cedula' => $clean]);
            } elseif (preg_match('/\b(?:c[eé]dula|cc|documento|nit|ced|cédula)[\s:]*([\d.,]{6,15})\b/iu', $msg, $m)) {
                $clean = preg_replace('/[^\d]/', '', $m[1]);
                if (preg_match('/^\d{6,12}$/', $clean)) {
                    $estado->cedula = $clean;
                    $cambio = true;
                    Log::info('🔍 Cédula capturada con prefijo', ['conv_id' => $conv->id, 'cedula' => $clean]);
                }
            }
        }

        // 2. EMAIL
        if (empty($estado->email) && preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $msg, $m)) {
            $estado->email = mb_strtolower($m[0]);
            $cambio = true;
            Log::info('🔍 Email capturado del mensaje', ['conv_id' => $conv->id, 'email' => $estado->email]);
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
            // Patrones RECOGER (cliente recoge)
            elseif (preg_match('/\b(recog[eo]|paso\s+a\s+recoger|paso\s+por|voy\s+a\s+pasar|yo\s+voy|reclamo|reclamar|recogerlo|en\s+la\s+sede|en\s+la\s+tienda|cliente\s+recoge|yo\s+recojo)\b/iu', $msgLowerMe)) {
                $estado->metodo_entrega = ConversacionPedidoEstado::METODO_RECOGER;
                $cambio = true;
                Log::info('🔍 Método entrega RECOGER capturado', ['conv_id' => $conv->id]);
            }
        }

        // 5. NOMBRE — heurística simple cuando estamos en paso identificación
        //    o datos_cliente_nuevo y el cliente envía un texto que parece nombre
        if (
            empty($estado->nombre_cliente) &&
            in_array($estado->paso_actual, [
                ConversacionPedidoEstado::PASO_IDENTIFICACION,
                ConversacionPedidoEstado::PASO_DATOS_CLIENTE,
            ], true)
        ) {
            // Heurística: el mensaje es 2-5 palabras, sin números, sin @, sin frases típicas
            $palabras = preg_split('/\s+/', trim($msg));
            $tieneSoloLetras = preg_match('/^[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+$/', $msg);
            $noEsFraseFuncional = !preg_match('/(quier|tien|necesi|domicil|recog|pago|gracias|hola|buen|por\s*favor|si|no|listo|dale)/i', $msg);
            if ($tieneSoloLetras && count($palabras) >= 2 && count($palabras) <= 5 && $noEsFraseFuncional) {
                $estado->nombre_cliente = mb_convert_case(trim($msg), MB_CASE_TITLE, 'UTF-8');
                $cambio = true;
                Log::info('🔍 Nombre capturado heurísticamente', [
                    'conv_id' => $conv->id,
                    'nombre'  => $estado->nombre_cliente,
                ]);
            }
        }

        if ($cambio) {
            $estado->save();
            $this->avanzarPaso($estado);
        }
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
