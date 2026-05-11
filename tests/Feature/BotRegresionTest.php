<?php

namespace Tests\Feature;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Models\Tenant;
use App\Services\Bots\HandoffHumanoService;
use App\Services\EstadoPedidoService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 🧪 TESTS DE REGRESIÓN — los 10 bugs históricos del bot.
 *
 * Si alguno de estos tests falla en CI, NO se debe desplegar a producción.
 * Cada test reproduce un bug real que pasó en producción y verifica que
 * el fix sigue activo.
 *
 * Ejecutar: php artisan test --filter BotRegresionTest
 */
class BotRegresionTest extends TestCase
{
    private ?Tenant $tenant = null;
    private ?ConversacionWhatsapp $conv = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant mínimo para tests
        $this->tenant = Tenant::first() ?? Tenant::create([
            'nombre' => 'Test Tenant',
            'slug'   => 'test-' . uniqid(),
            'activo' => true,
        ]);

        app(TenantManager::class)->set($this->tenant);

        // Conversación de prueba (transitoria)
        $this->conv = ConversacionWhatsapp::create([
            'tenant_id'             => $this->tenant->id,
            'telefono_normalizado'  => '5731' . random_int(10000000, 99999999),
            'canal'                 => 'whatsapp',
            'estado'                => 'activa',
        ]);
    }

    protected function tearDown(): void
    {
        // Limpiar estado y conv de prueba
        if ($this->conv) {
            ConversacionPedidoEstado::where('conversacion_id', $this->conv->id)->delete();
            $this->conv->delete();
        }
        parent::tearDown();
    }

    /** @test BUG #1: Captador no debe inventar cédula de números en dirección */
    public function captador_no_confunde_direccion_con_cedula(): void
    {
        $service = app(EstadoPedidoService::class);
        $service->captarDelMensajeUsuario($this->conv, 'Sí cra 50 # 51-00');

        $estado = $service->obtener($this->conv);
        $this->assertEmpty($estado->cedula, 'Captador NO debe tomar dígitos de dirección como cédula');
    }

    /** @test BUG #2: Captador no debe tomar teléfono como cédula */
    public function captador_no_confunde_telefono_con_cedula(): void
    {
        $service = app(EstadoPedidoService::class);
        $service->captarDelMensajeUsuario($this->conv, 'Voy a pagar en transferencia 3216499744');

        $estado = $service->obtener($this->conv);
        $this->assertEmpty($estado->cedula, 'Captador NO debe tomar teléfono Nequi como cédula');
    }

    /** @test BUG #3: Captador SÍ debe detectar cédula real */
    public function captador_si_detecta_cedula_real(): void
    {
        $service = app(EstadoPedidoService::class);
        $service->captarDelMensajeUsuario($this->conv, '1007767612');

        $estado = $service->obtener($this->conv);
        $this->assertEquals('1007767612', $estado->cedula);
    }

    /** @test BUG #4: Productos se acumulan (no se reemplazan) */
    public function captador_acumula_multiples_productos(): void
    {
        $service = app(EstadoPedidoService::class);

        $service->captarDelMensajeUsuario($this->conv, '2 kilos de pierna de cerdo');
        $service->captarDelMensajeUsuario($this->conv, 'y 1 kilo de tilapia');
        $service->captarDelMensajeUsuario($this->conv, 'también 500 gramos de pollo');

        $estado = $service->obtener($this->conv);
        $cantidad = is_array($estado->productos) ? count($estado->productos) : 0;
        $this->assertGreaterThanOrEqual(2, $cantidad,
            'Captador debe acumular productos de mensajes separados, no reemplazar');
    }

    /** @test BUG #5: Captador extrae nombre incluso si hay cédula/teléfono en el mismo mensaje */
    public function captador_extrae_nombre_de_mensaje_mixto(): void
    {
        $service = app(EstadoPedidoService::class);
        $service->captarDelMensajeUsuario($this->conv, 'Vanesa Castaño Sánchez 3155434093');

        $estado = $service->obtener($this->conv);
        $this->assertNotEmpty($estado->nombre_cliente);
        $this->assertStringContainsString('Vanesa', $estado->nombre_cliente);
    }

    /** @test BUG #6: Detector de frustración por repetición */
    public function handoff_detecta_frustracion_por_repeticion(): void
    {
        $handoff = app(HandoffHumanoService::class);

        $reflection = new \ReflectionMethod($handoff, 'expresoFrustracion');
        $reflection->setAccessible(true);

        $frases = [
            'ya te había dicho que quería tilapia',
            'te lo repito',
            'esto ya te lo dije',
            'por qué me pides otra vez lo mismo',
        ];

        foreach ($frases as $f) {
            $detecta = $reflection->invoke($handoff, mb_strtolower($f));
            $this->assertTrue($detecta, "Debería detectar frustración en: '{$f}'");
        }
    }

    /** @test BUG #7: Detector de petición humana */
    public function handoff_detecta_peticion_humano(): void
    {
        $handoff = app(HandoffHumanoService::class);

        $reflection = new \ReflectionMethod($handoff, 'piedeHumano');
        $reflection->setAccessible(true);

        $frases = [
            'quiero hablar con un asesor',
            'necesito un humano',
            'pásame con una persona real',
            'no me entiendes nada',
        ];

        foreach ($frases as $f) {
            $detecta = $reflection->invoke($handoff, mb_strtolower($f));
            $this->assertTrue($detecta, "Debería detectar petición humano en: '{$f}'");
        }
    }

    /** @test BUG #8: Captador detecta dirección con formato libre */
    public function captador_detecta_direcciones_diversas(): void
    {
        $service = app(EstadoPedidoService::class);
        $reflection = new \ReflectionMethod($service, 'detectarDireccionEnMensaje');
        $reflection->setAccessible(true);

        $direcciones = [
            'Calle 50 #63B-48',
            'Cra 50 #45 23',
            'Calle 41 #59bb 35, Apto 1214',
            'Diagonal 30 sur 15-20',
            'Vivo en la calle 80 número 45',
        ];

        foreach ($direcciones as $d) {
            $resultado = $reflection->invoke($service, $d);
            $this->assertNotNull($resultado, "Debería detectar dirección en: '{$d}'");
        }
    }

    /** @test BUG #9: Captador NO detecta dirección en saludos */
    public function captador_no_confunde_saludos_con_direccion(): void
    {
        $service = app(EstadoPedidoService::class);
        $reflection = new \ReflectionMethod($service, 'detectarDireccionEnMensaje');
        $reflection->setAccessible(true);

        $noEsDir = ['Hola', 'Gracias', 'Buenas noches', 'OK', '1007767612'];

        foreach ($noEsDir as $msg) {
            $resultado = $reflection->invoke($service, $msg);
            $this->assertNull($resultado, "NO debería detectar dirección en: '{$msg}'");
        }
    }

    /** @test BUG #10: aOrderData incluye todos los campos requeridos */
    public function estado_genera_orderdata_completo(): void
    {
        $estado = app(EstadoPedidoService::class)->obtener($this->conv);
        $estado->productos = [
            ['code' => '103', 'name' => 'PIERNA DE CERDO', 'quantity' => 1.0, 'unit' => 'Kl'],
        ];
        $estado->cedula = '1007767612';
        $estado->nombre_cliente = 'Edgar Stiben Madrid';
        $estado->email = 'test@test.com';
        $estado->direccion = 'Cra 50 #63B-48';
        $estado->metodo_entrega = ConversacionPedidoEstado::METODO_DOMICILIO;
        $estado->cobertura_validada = true;
        $estado->save();

        $orderData = $estado->aOrderData();

        $this->assertArrayHasKey('products', $orderData);
        $this->assertArrayHasKey('cedula', $orderData);
        $this->assertArrayHasKey('customer_name', $orderData);
        $this->assertArrayHasKey('address', $orderData);
        $this->assertNotEmpty($orderData['cedula']);
        $this->assertCount(1, $orderData['products']);
    }
}
