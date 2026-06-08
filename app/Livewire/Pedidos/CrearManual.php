<?php

namespace App\Livewire\Pedidos;

use App\Http\Controllers\WhatsappWebhookController;
use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Models\Producto;
use App\Models\Sede;
use App\Services\ConversacionService;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * 🛒 CREAR PEDIDO MANUAL
 *
 * Permite al admin/operador crear un pedido directamente sin que pase por
 * el bot. Reusa la lógica de WhatsappWebhookController::guardarPedidoDesdeToolCall
 * para que se cree con los mismos eventos, exportación al ERP, etc.
 *
 * Modos de uso:
 *  - Standalone (/pedidos/crear): formulario en blanco
 *  - Desde chat (?conv=N): pre-carga datos del estado del pedido de esa
 *    conversación para que el operador termine de cerrar lo que el bot
 *    no pudo.
 */
class CrearManual extends Component
{
    // Origen
    public ?int $conversacionId = null;

    // Cliente
    public string $telefono       = '';
    public string $nombre_cliente = '';
    public string $cedula         = '';
    public string $email          = '';

    // Productos
    /** @var array<int, array{producto_id:int|null,nombre:string,cantidad:float,unidad:string,precio:float}> */
    public array $productos = [];

    // Entrega
    public string $metodo_entrega = 'recoger'; // recoger | domicilio
    public ?int   $sede_id        = null;
    public string $direccion      = '';
    public string $barrio         = '';
    public string $ciudad         = '';

    // 🛵 Domiciliario (modo híbrido: el sistema sugiere, el operador confirma).
    public ?int  $domiciliario_id        = null;
    public bool  $domiciliarioSugerido   = false; // true si lo puso el sistema

    // Pago / extras
    public string $metodo_pago = 'efectivo';
    public string $cupon       = '';
    public string $notas       = '';

    // Productos disponibles para autocomplete
    public string $busquedaProducto = '';

    // 🏷️ Marca que el teléfono fue traído del ERP (HGI) al buscar por cédula,
    //    para que el operador lo verifique.
    public bool $telefonoDesdeErp = false;

    // 💰 Lista de precios HGI (1..8) del cliente identificado. null = precio base.
    public ?int $listaPrecioCliente = null;

    // Cuando el teléfono parece inválido, exigimos una segunda confirmación.
    public bool $confirmoTelefonoSospechoso = false;

    // 💳 Link de pago generado tras crear el pedido (para mostrar/copiar).
    public ?string $linkPagoGenerado = null;
    public ?int    $pedidoCreadoId   = null;

    public function mount(?int $conv = null): void
    {
        $this->conversacionId = $conv;
        if ($conv) {
            $this->precargarDesdeConversacion($conv);
        }
    }

    private function precargarDesdeConversacion(int $convId): void
    {
        $conv = ConversacionWhatsapp::with('cliente')->find($convId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // Datos básicos
        $this->telefono = $conv->telefono_normalizado;
        $this->nombre_cliente = $conv->cliente?->nombre ?? '';

        // Estado estructurado del pedido
        try {
            $estado = app(EstadoPedidoService::class)->obtener($conv);
            if ($estado->cedula)        $this->cedula = $estado->cedula;
            if ($estado->nombre_cliente && empty($this->nombre_cliente)) $this->nombre_cliente = $estado->nombre_cliente;
            if ($estado->email)         $this->email = $estado->email;
            if ($estado->metodo_entrega) $this->metodo_entrega = $estado->metodo_entrega;
            if ($estado->sede_id)       $this->sede_id = $estado->sede_id;
            if ($estado->direccion)     $this->direccion = $estado->direccion;
            if ($estado->barrio)        $this->barrio = $estado->barrio;
            if ($estado->ciudad)        $this->ciudad = $estado->ciudad;
            if ($estado->metodo_pago)   $this->metodo_pago = $estado->metodo_pago;
            if ($estado->cupon_code)    $this->cupon = $estado->cupon_code;
            if ($estado->notas)         $this->notas = $estado->notas;

            if (!empty($estado->productos)) {
                foreach ($estado->productos as $p) {
                    // Tratar de match con catálogo
                    $prodMatch = Producto::where('nombre', 'LIKE', '%' . ($p['name'] ?? '') . '%')->first();
                    $this->productos[] = [
                        'producto_id' => $prodMatch?->id,
                        'nombre'      => $p['name'] ?? '',
                        'cantidad'    => (float) ($p['quantity'] ?? 1),
                        'unidad'      => $p['unit'] ?? 'unidad',
                        'precio'      => (float) ($prodMatch?->precio_base ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo precargar estado: ' . $e->getMessage());
        }
    }

    public function getProductosCatalogoProperty()
    {
        if (mb_strlen($this->busquedaProducto) < 2) {
            return collect();
        }

        // 🎯 Usar el MISMO motor del bot (BotCatalogoService): si el tenant está
        //    en modo integración (HGI), trae productos y precios REALES del ERP
        //    en vivo; si está en modo tabla, lee la tabla local. Respeta la
        //    config de cada tenant automáticamente.
        try {
            $q = mb_strtolower(trim($this->busquedaProducto));
            $catalogo = app(\App\Services\BotCatalogoService::class)
                ->productosActivos($this->sede_id ?: null, $this->listaPrecioCliente);

            return $catalogo
                ->filter(function ($p) use ($q) {
                    $nombre = mb_strtolower((string) ($p->nombre ?? ''));
                    $codigo = mb_strtolower((string) ($p->codigo ?? ''));
                    return str_contains($nombre, $q) || str_contains($codigo, $q);
                })
                ->take(15)
                ->map(fn ($p) => (object) [
                    'id'          => $p->id ?? null,
                    'codigo'      => (string) ($p->codigo ?? ''),
                    'nombre'      => (string) ($p->nombre ?? ''),
                    'precio_base' => (float) ($p->precio_base ?? 0),
                    'unidad'      => (string) ($p->unidad ?? 'unidad'),
                ])
                ->values();
        } catch (\Throwable $e) {
            Log::warning('Pedido manual: catálogo HGI falló, fallback a local', [
                'error' => $e->getMessage(),
            ]);
            // Fallback: tabla local si el ERP está caído
            return Producto::where('activo', true)
                ->where(function ($q) {
                    $q->where('nombre', 'LIKE', '%' . $this->busquedaProducto . '%')
                      ->orWhere('codigo', 'LIKE', '%' . $this->busquedaProducto . '%');
                })
                ->limit(10)
                ->get(['id', 'codigo', 'nombre', 'precio_base', 'unidad']);
        }
    }

    public function getSedesProperty()
    {
        return Sede::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /** 🛵 Domiciliarios activos para el selector. */
    public function getDomiciliariosProperty()
    {
        return \App\Models\Domiciliario::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estado']);
    }

    /**
     * 💡 Sugiere el mejor domiciliario (sistema) según la zona/carga.
     * No guarda nada: solo pre-selecciona para que el operador confirme/cambie.
     */
    public function sugerirDomiciliario(): void
    {
        try {
            // Pedido temporal con los datos actuales para que el servicio razone.
            $tmp = new \App\Models\Pedido();
            $tmp->barrio = $this->barrio ?: null;

            $dom = app(\App\Services\AsignacionDomiciliarioService::class)->sugerirSinGuardar($tmp);
            if ($dom) {
                $this->domiciliario_id      = $dom->id;
                $this->domiciliarioSugerido = true;
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Domiciliario sugerido: ' . $dom->nombre]);
            } else {
                $this->dispatch('notify', ['type' => 'info', 'message' => 'No hay domiciliarios disponibles para sugerir.']);
            }
        } catch (\Throwable $e) {
            Log::warning('Sugerir domiciliario falló: ' . $e->getMessage());
        }
    }

    /** Si el operador cambia el domiciliario a mano, ya no es "sugerido". */
    public function updatedDomiciliarioId(): void
    {
        $this->domiciliarioSugerido = false;
    }

    /**
     * Agrega un producto al pedido por su CÓDIGO. Usamos código (no id) porque
     * los productos que vienen SOLO de HGI no tienen id local. Busca en el
     * mismo catálogo (BotCatalogoService) para traer nombre/precio/unidad reales.
     */
    public function agregarProducto($ref): void
    {
        $ref = trim((string) $ref);
        if ($ref === '') return;

        // $ref puede ser el CÓDIGO del producto o su ID local. Buscamos por
        // ambos para que funcione siempre (los productos de HGI vienen con id
        // local cuando hay match, o solo código cuando no).
        $prod = null;
        try {
            $prod = app(\App\Services\BotCatalogoService::class)
                ->productosActivos($this->sede_id ?: null, $this->listaPrecioCliente)
                ->first(fn ($p) =>
                    (string) ($p->codigo ?? '') === $ref
                    || (string) ($p->id ?? '') === $ref
                );
        } catch (\Throwable $e) {
            Log::warning('Pedido manual: agregar producto, catálogo falló', ['error' => $e->getMessage()]);
        }

        // Fallback: tabla local por código o por id.
        if (!$prod) {
            $prod = Producto::where('codigo', $ref)->orWhere('id', $ref)->first();
        }
        if (!$prod) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo agregar el producto.']);
            return;
        }

        $this->productos[] = [
            'producto_id' => $prod->id ?? null,
            'codigo'      => (string) ($prod->codigo ?? ''),
            'nombre'      => $prod->nombre,
            'cantidad'    => 1,
            'unidad'      => $prod->unidad ?: 'unidad',
            'precio'      => (float) ($prod->precio_base ?? 0),
        ];
        $this->busquedaProducto = '';
    }

    public function eliminarProducto(int $idx): void
    {
        unset($this->productos[$idx]);
        $this->productos = array_values($this->productos);
    }

    public function getTotalProperty(): float
    {
        return collect($this->productos)->sum(fn ($p) => (float) ($p['cantidad'] ?? 0) * (float) ($p['precio'] ?? 0));
    }

    /** Si el operador edita el teléfono a mano, ya no es "el del ERP". */
    public function updatedTelefono(): void
    {
        $this->telefonoDesdeErp = false;
    }

    /**
     * Heurística: ¿el teléfono parece inválido / de relleno?
     * Reglas (Colombia): celular = 10 dígitos empezando por 3. También
     * detecta números repetidos como 3111111111, 0000000000, etc.
     * Devuelve string con el motivo, o '' si parece válido.
     */
    public function telefonoSospechoso(): string
    {
        $tel = $this->normalizarTel($this->telefono);
        if ($tel === '') return '';
        // ✍️ Mientras todavía escribe (menos de 10 dígitos) NO molestar con error.
        //    Solo validamos cuando el número ya está "completo" (10+ dígitos).
        if (strlen($tel) < 10) {
            return '';
        }
        if (strlen($tel) > 10) {
            return 'Tiene más de 10 dígitos. Un celular colombiano son 10.';
        }
        if ($tel[0] !== '3') {
            return 'Un celular colombiano empieza por 3.';
        }
        if (preg_match('/^3(\d)\1{8}$/', $tel)) {
            return 'Parece un número de relleno (dígitos repetidos).';
        }
        return '';
    }

    /**
     * Buscar cliente por cédula en BD local (luego se podría extender a ERP).
     */
    public function buscarPorCedula(): void
    {
        if (mb_strlen($this->cedula) < 5) return;

        // 🧹 Limpiar datos del cliente ANTERIOR para no arrastrar (ej. el email
        //    de otro cliente cuando se busca una cédula distinta sin recargar).
        $this->nombre_cliente      = '';
        $this->email               = '';
        $this->telefono            = '';
        $this->direccion           = '';
        $this->barrio              = '';
        $this->telefonoDesdeErp    = false;
        $this->listaPrecioCliente  = null;

        // 1️⃣ Buscar en la base local (rápido). Llena nombre/email/dirección,
        //    y el teléfono SOLO si el local tiene uno VÁLIDO.
        $cliente = Cliente::where('cedula', $this->cedula)->first();
        $encontradoLocal = false;
        if ($cliente) {
            $encontradoLocal = true;
            $this->nombre_cliente = $cliente->nombre;
            $this->email = $cliente->email ?? '';
            $telLocal = $this->normalizarTel($cliente->telefono_normalizado ?? $cliente->telefono ?? '');
            if ($this->esTelefonoValido($telLocal)) {
                $this->telefono = $telLocal;
            }
        }

        // 2️⃣ Consultar SIEMPRE el ERP (HGI) si está activo. El ERP suele tener
        //    el dato más actualizado; lo usamos para completar lo que falte y
        //    para CORREGIR el teléfono si el local quedó inválido (ej. relleno
        //    3111111111 mientras HGI tiene el celular real).
        $hitErp = $this->buscarClienteEnErp();

        if ($encontradoLocal && !$hitErp) {
            // Estaba local pero el ERP no respondió (o no aplica). Avisar igual.
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente encontrado: ' . $this->nombre_cliente]);
        }

        if (!$encontradoLocal && !$hitErp) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No se encontró un cliente con esa cédula.']);
        }
    }

    /** Normaliza un teléfono: solo dígitos, sin prefijo país 57. */
    private function normalizarTel($valor): string
    {
        $tel = preg_replace('/\D+/', '', (string) $valor);
        if (str_starts_with($tel, '57') && strlen($tel) === 12) {
            $tel = substr($tel, 2);
        }
        return $tel;
    }

    /** ¿Es un celular colombiano válido? (10 dígitos, empieza por 3, no relleno) */
    private function esTelefonoValido($tel): bool
    {
        $tel = $this->normalizarTel($tel);
        return strlen($tel) === 10
            && $tel[0] === '3'
            && !preg_match('/^3(\d)\1{8}$/', $tel);
    }

    /**
     * Devuelve el teléfono en formato INTERNACIONAL para Meta/WhatsApp:
     * 57 + 10 dígitos (Colombia). Si ya viene con 57, lo respeta.
     */
    private function telefonoInternacional($tel): string
    {
        $tel = preg_replace('/\D+/', '', (string) $tel);
        // Ya en internacional (57XXXXXXXXXX)
        if (strlen($tel) === 12 && str_starts_with($tel, '57')) {
            return $tel;
        }
        // Celular local de 10 dígitos → anteponer 57
        if (strlen($tel) === 10 && $tel[0] === '3') {
            return '57' . $tel;
        }
        return $tel; // cualquier otro caso, devolver tal cual (no forzar)
    }

    /**
     * Busca el cliente en el ERP (HGI u otro) SOLO si el tenant tiene una
     * integración activa con cliente_lookup habilitado. Devuelve true si lo
     * encontró y autocompletó los campos.
     */
    private function buscarClienteEnErp(): bool
    {
        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();

            $integ = \App\Models\Integracion::where('tenant_id', $tenantId)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

            // El tenant no usa ERP para buscar clientes → no hay nada que hacer.
            if (!$integ) {
                return false;
            }

            $clienteErp = app(\App\Services\ClienteErpService::class)
                ->buscar($integ, $this->cedula, $this->telefono ?: null);

            if (!$clienteErp) {
                return false;
            }

            // 💰 Resolver la lista de precios del cliente (HGI IntPrecio 1..8).
            $this->listaPrecioCliente = app(\App\Services\ClienteErpService::class)
                ->obtenerListaPrecioCliente($integ, $this->cedula);

            // Autocompletar con los datos REALES del ERP.
            $this->nombre_cliente = $clienteErp['StrNombre'] ?? $this->nombre_cliente;

            // 📞 Teléfono: si el actual está VACÍO o es INVÁLIDO (ej. el local
            //    quedó en 3111111111), y el ERP tiene uno VÁLIDO, usar el del
            //    ERP. Así el celular real de HGI corrige el basura local.
            $telErp = $this->normalizarTel($clienteErp['StrCelular'] ?? '');
            if (!$this->esTelefonoValido($this->telefono) && $this->esTelefonoValido($telErp)) {
                $this->telefono = $telErp;
                $this->telefonoDesdeErp = true; // 🏷️ marcar que vino del ERP
            }
            if (empty($this->direccion) && !empty($clienteErp['StrDireccion'])) {
                $this->direccion = $clienteErp['StrDireccion'];
            }
            // 📧 Email: HGI lo guarda en StrMail (correo) o StrMailFE (facturación
            //    electrónica). Tomamos el primero que tenga un valor con @.
            if (empty($this->email)) {
                foreach (['StrMail', 'StrMailFE'] as $colMail) {
                    $mail = trim((string) ($clienteErp[$colMail] ?? ''));
                    if ($mail !== '' && str_contains($mail, '@')) {
                        $this->email = $mail;
                        break;
                    }
                }
            }

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => 'Cliente encontrado en el ERP: ' . ($clienteErp['StrNombre'] ?? $this->cedula),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Pedido manual: fallo buscando cliente en ERP', [
                'cedula' => $this->cedula, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function crearPedido()
    {
        // Validación mínima
        if (empty($this->productos)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Debes agregar al menos un producto.']);
            return;
        }
        if (empty($this->telefono)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Falta el teléfono del cliente.']);
            return;
        }
        // ⚠️ Teléfono sospechoso → frenar y pedir confirmación una vez.
        $motivoTel = $this->telefonoSospechoso();
        if ($motivoTel !== '' && !$this->confirmoTelefonoSospechoso) {
            $this->confirmoTelefonoSospechoso = true; // próximo clic ya pasa
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "⚠️ Revisa el teléfono: {$motivoTel} El cliente NO recibirá notificaciones. Si está bien, dale 'Crear pedido' otra vez.",
            ]);
            return;
        }
        if (empty($this->nombre_cliente)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Falta el nombre del cliente.']);
            return;
        }
        if ($this->metodo_entrega === 'domicilio' && empty($this->direccion)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Falta la dirección para domicilio.']);
            return;
        }
        if ($this->metodo_entrega === 'recoger' && empty($this->sede_id)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Falta la sede de recogida.']);
            return;
        }

        // 📞 Teléfono en formato INTERNACIONAL (57 + 10 dígitos) para que Meta
        //    pueda entregar el mensaje. Sin el 57, WhatsApp no lo entrega.
        $telInternacional = $this->telefonoInternacional($this->telefono);

        // Construir orderData con el formato que entiende guardarPedidoDesdeToolCall
        $orderData = [
            'products' => array_values(array_map(fn ($p) => [
                'name'     => $p['nombre'],
                'quantity' => (float) $p['cantidad'],
                'unit'     => $p['unidad'],
            ], $this->productos)),
            'customer_name'  => $this->nombre_cliente,
            'cedula'         => $this->cedula,
            'phone'          => $telInternacional,
            'email'          => $this->email,
            'payment_method' => $this->metodo_pago,
            'coupon_code'    => $this->cupon,
            'notes'          => trim(($this->notas ? $this->notas . "\n" : '') . '[CREADO MANUALMENTE]'),
            // 🚩 Marca que es un pedido manual → salta el cortafuego anti-fantasma
            //    del bot (el operador agrega productos a mano).
            'manual'         => true,
        ];

        if ($this->metodo_entrega === 'domicilio') {
            $orderData['address']      = $this->direccion;
            $orderData['neighborhood'] = $this->barrio;
            $orderData['location']     = $this->ciudad;
        } else {
            $orderData['address']  = '';
            $orderData['location'] = Sede::find($this->sede_id)?->nombre ?? '';
            $orderData['pickup']   = true;
            $orderData['sede_id']  = $this->sede_id;
        }

        try {
            // Encontrar o crear conversación para enganche
            $conv = $this->conversacionId
                ? ConversacionWhatsapp::find($this->conversacionId)
                : ConversacionWhatsapp::firstOrCreate(
                    ['telefono_normalizado' => $telInternacional],
                    [
                        'tenant_id' => app(\App\Services\TenantManager::class)->id(),
                        'estado'    => 'activa',
                        'canal'     => 'manual',
                    ]
                );

            $convService = app(ConversacionService::class);
            $cacheKey    = 'manual_' . Str::random(8);

            $controller = app(WhatsappWebhookController::class);
            $resultado = $controller->guardarPedidoDesdeToolCall(
                $orderData,
                $telInternacional,
                $this->nombre_cliente,
                [], // historial vacío
                $cacheKey,
                $conv->connection_id ? (string) $conv->connection_id : null,
                $conv,
                $convService
            );

            // Marcar el estado como confirmado + notificar al cliente
            try {
                // Buscar el último pedido del teléfono creado en los últimos 30s
                $pedido = \App\Models\Pedido::where('telefono', $telInternacional)
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->orderByDesc('id')
                    ->first();
                if ($pedido) {
                    app(EstadoPedidoService::class)->marcarConfirmado($conv, $pedido->id);

                    // 🛵 Asignar domiciliario (solo a domicilio). Si el operador
                    //    eligió uno, ese; si no, el sistema sugiere el mejor.
                    if ($this->metodo_entrega === 'domicilio') {
                        try {
                            $domId = $this->domiciliario_id;
                            if (!$domId) {
                                $sug = app(\App\Services\AsignacionDomiciliarioService::class)
                                    ->sugerirSinGuardar($pedido);
                                $domId = $sug?->id;
                            }
                            if ($domId) {
                                $pedido->domiciliario_id = $domId;
                                $pedido->fecha_asignacion_domiciliario = now();
                                $pedido->saveQuietly();
                                $dom = \App\Models\Domiciliario::find($domId);
                                if ($dom && $dom->estado === \App\Models\Domiciliario::ESTADO_DISPONIBLE) {
                                    $dom->update(['estado' => \App\Models\Domiciliario::ESTADO_EN_RUTA]);
                                }
                            }
                        } catch (\Throwable $eDom) {
                            Log::warning('Pedido manual: no se pudo asignar domiciliario', [
                                'pedido_id' => $pedido->id, 'error' => $eDom->getMessage(),
                            ]);
                        }
                    }

                    // 💳 Generar link de pago (si el método es por pasarela), para
                    //    INCLUIRLO en el mensaje al cliente y mostrarlo en pantalla.
                    $linkPago = null;
                    if (in_array($this->metodo_pago, ['wompi', 'bold', 'link', 'tarjeta'], true)) {
                        try {
                            $linkPago = app(\App\Services\PasarelaPagoService::class)->urlPagoPrincipal($pedido);
                            if ($linkPago) {
                                $this->linkPagoGenerado = $linkPago;
                                $this->pedidoCreadoId   = $pedido->id;
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Pedido manual: no se pudo generar link de pago', ['error' => $e->getMessage()]);
                        }
                    }

                    // 📲 Notificar al cliente Y dejar TRAZABILIDAD en el chat.
                    $this->notificarClienteConTraza($conv, $pedido, $linkPago);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo marcar estado confirmado: ' . $e->getMessage());
            }

            // 💳 Si se generó link de pago, mostrarlo en pantalla en vez de redirigir.
            if ($this->linkPagoGenerado) {
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => 'Pedido creado. Link de pago generado abajo y enviado al cliente.',
                ]);
                return; // no redirigir: mostrar el link
            }

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => '✅ Pedido creado y cliente notificado.',
            ]);

            return redirect()->route('pedidos.index');
        } catch (\Throwable $e) {
            Log::error('Error creando pedido manual: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 📲 Notifica al cliente que su pedido fue creado y DEJA TRAZABILIDAD en el
     * chat (registra el mensaje en la conversación). Estrategia:
     *   - Si la ventana de 24h está ABIERTA → envía texto libre (con link de
     *     pago si lo hay) y lo registra como mensaje del asistente en el chat.
     *   - Si está CERRADA → dispara la plantilla Meta 'pedido_confirmado' (que
     *     funciona fuera de la ventana) y registra una nota en el chat con el
     *     resumen + link, para que quede el historial.
     */
    private function notificarClienteConTraza($conv, $pedido, ?string $linkPago): void
    {
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            $esMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;

            // Texto de confirmación legible (con link de pago si existe).
            $total = number_format((float) ($pedido->total ?? 0), 0, ',', '.');
            $texto = "✅ *¡Pedido recibido!*\n"
                . "Hola {$this->nombre_cliente}, registramos tu pedido *#{$pedido->id}* por *\${$total}*.\n";
            if ($linkPago) {
                $texto .= "\n💳 *Paga aquí con tarjeta, Nequi o PSE:*\n{$linkPago}\n";
            }
            $texto .= "\n¡Gracias por tu compra! 🙌";

            $convService = app(ConversacionService::class);
            $enviadoComoTexto = false;

            if ($esMeta) {
                // ¿Ventana de 24h abierta? Solo entonces se puede mandar texto libre.
                $ventanaAbierta = false;
                try {
                    $ventanaAbierta = app(\App\Services\Whatsapp\Ventana24hChecker::class)->abierta($conv);
                } catch (\Throwable $e) { /* asumir cerrada */ }

                if ($ventanaAbierta) {
                    $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                        ->enviarTexto($conv->telefono_normalizado, $texto, $conv->tenant_id);
                    $enviadoComoTexto = (bool) $ok;
                }

                // Si NO se pudo enviar texto (ventana cerrada) → plantilla.
                if (!$enviadoComoTexto) {
                    app(\App\Services\Whatsapp\DispararEventoMetaService::class)
                        ->dispararParaPedido('pedido_confirmado', $pedido);
                }
            }

            // 🧾 TRAZABILIDAD: registrar el mensaje en la conversación (chat).
            $contenidoTraza = $enviadoComoTexto
                ? $texto
                : ($texto . ($esMeta ? "\n\n_(enviado al cliente como plantilla — ventana de 24h cerrada)_" : ''));

            $convService->agregarMensaje(
                $conv,
                \App\Models\MensajeWhatsapp::ROL_ASSISTANT,
                $contenidoTraza,
                ['meta' => [
                    'enviado_por_humano' => true,
                    'usuario_id'         => auth()->id(),
                    'pedido_manual'      => true,
                    'pedido_id'          => $pedido->id,
                    'link_pago'          => $linkPago,
                ]]
            );
        } catch (\Throwable $e) {
            Log::warning('Pedido manual: notificar+traza falló', [
                'pedido_id' => $pedido->id ?? null, 'error' => $e->getMessage(),
            ]);
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        // 🗺️ API key de Google Maps del tenant (para autocompletar dirección).
        $tenant = app(\App\Services\TenantManager::class)->current();
        $gmapsKey = ($tenant && $tenant->google_maps_activo && !empty($tenant->google_maps_api_key))
            ? $tenant->google_maps_api_key
            : null;

        // 💳 Pasarelas de pago activas del tenant.
        $tieneWompi = $tenant ? $tenant->tieneWompi() : false;
        $tieneBold  = $tenant ? ($tenant->bold_activo && !empty($tenant->bold_api_key)) : false;

        return view('livewire.pedidos.crear-manual', [
            'productosCatalogo' => $this->productosCatalogo,
            'sedes'             => $this->sedes,
            'domiciliarios'     => $this->domiciliarios,
            'gmapsKey'          => $gmapsKey,
            'tieneWompi'        => $tieneWompi,
            'tieneBold'         => $tieneBold,
        ]);
    }
}
