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

    // Pago / extras
    public string $metodo_pago = 'efectivo';
    public string $cupon       = '';
    public string $notas       = '';

    // Productos disponibles para autocomplete
    public string $busquedaProducto = '';

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
                ->productosActivos($this->sede_id ?: null);

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
                ->productosActivos($this->sede_id ?: null)
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
        return collect($this->productos)->sum(fn ($p) => $p['cantidad'] * $p['precio']);
    }

    /**
     * Buscar cliente por cédula en BD local (luego se podría extender a ERP).
     */
    public function buscarPorCedula(): void
    {
        if (mb_strlen($this->cedula) < 5) return;

        // 1️⃣ Primero buscar en la base local (rápido). Si el cliente ya pidió
        //    alguna vez por la plataforma, está acá.
        $cliente = Cliente::where('cedula', $this->cedula)->first();
        if ($cliente) {
            $this->nombre_cliente = $cliente->nombre;
            $this->telefono = $this->telefono ?: $cliente->telefono_normalizado;
            $this->email = $this->email ?: ($cliente->email ?? '');
            $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Cliente encontrado: ' . $cliente->nombre]);
            return;
        }

        // 2️⃣ Si NO está local, y el tenant tiene HGI/ERP con búsqueda de cliente
        //    activada, consultarlo en el ERP. Esto respeta la config de cada
        //    tenant: si no tiene lookup ERP, simplemente no encuentra nada.
        if ($this->buscarClienteEnErp()) {
            return;
        }

        $this->dispatch('notify', ['type' => 'info', 'message' => 'No se encontró un cliente con esa cédula.']);
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

            // Autocompletar con los datos REALES del ERP.
            $this->nombre_cliente = $clienteErp['StrNombre'] ?? $this->nombre_cliente;
            if (empty($this->telefono) && !empty($clienteErp['StrCelular'])) {
                $this->telefono = $clienteErp['StrCelular'];
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
                'message' => '✅ Cliente encontrado en el ERP: ' . ($clienteErp['StrNombre'] ?? $this->cedula),
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

        // Construir orderData con el formato que entiende guardarPedidoDesdeToolCall
        $orderData = [
            'products' => array_values(array_map(fn ($p) => [
                'name'     => $p['nombre'],
                'quantity' => (float) $p['cantidad'],
                'unit'     => $p['unidad'],
            ], $this->productos)),
            'customer_name'  => $this->nombre_cliente,
            'cedula'         => $this->cedula,
            'phone'          => $this->telefono,
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
                    ['telefono_normalizado' => $this->telefono],
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
                $this->telefono,
                $this->nombre_cliente,
                [], // historial vacío
                $cacheKey,
                $conv->connection_id ? (string) $conv->connection_id : null,
                $conv,
                $convService
            );

            // Marcar el estado como confirmado
            try {
                // Buscar el último pedido del teléfono creado en los últimos 30s
                $pedido = \App\Models\Pedido::where('telefono', $this->telefono)
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->orderByDesc('id')
                    ->first();
                if ($pedido) {
                    app(EstadoPedidoService::class)->marcarConfirmado($conv, $pedido->id);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo marcar estado confirmado: ' . $e->getMessage());
            }

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => '✅ Pedido creado correctamente.',
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

    #[Layout('layouts.app')]
    public function render()
    {
        // 🗺️ API key de Google Maps del tenant (para autocompletar dirección).
        $tenant = app(\App\Services\TenantManager::class)->current();
        $gmapsKey = ($tenant && $tenant->google_maps_activo && !empty($tenant->google_maps_api_key))
            ? $tenant->google_maps_api_key
            : null;

        return view('livewire.pedidos.crear-manual', [
            'productosCatalogo' => $this->productosCatalogo,
            'sedes'             => $this->sedes,
            'gmapsKey'          => $gmapsKey,
        ]);
    }
}
