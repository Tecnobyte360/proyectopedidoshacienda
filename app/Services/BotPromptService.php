<?php

namespace App\Services;

use App\Models\ConfiguracionBot;

/**
 * Renderiza el system prompt del bot reemplazando variables/placeholders.
 *
 * Variables soportadas (formato {nombre_variable}):
 *   {nombre_asesora}     → Nombre de la asesora (default Sofía)
 *   {cliente_nombre}     → Nombre del cliente actual
 *   {saludo_hora}        → "buenos días/tardes/noches" según la hora
 *   {fecha_actual}       → Fecha actual formateada
 *   {hora_actual}        → Hora actual
 *   {empresa}            → Bloque de info de la empresa
 *   {catalogo}           → Catálogo formateado de productos
 *   {promociones}        → Promociones vigentes
 *   {zonas}              → Zonas de cobertura
 *   {historial_cliente}  → Últimos pedidos del cliente
 *   {ans}                → Reglas ANS de cancelación/modificación
 *   {nota_imagenes}      → Instrucciones del tool de imágenes (si activo)
 */
class BotPromptService
{
    public function __construct(
        private BotCatalogoService $catalogo,
    ) {}

    /**
     * Catálogo de variables disponibles (para mostrar en el editor UI).
     */
    public static function variablesDisponibles(): array
    {
        return [
            ['key' => 'nombre_asesora',    'descripcion' => 'Nombre de la asesora (Sofía u otro)'],
            ['key' => 'cliente_nombre',    'descripcion' => 'Nombre del cliente que está chateando'],
            ['key' => 'saludo_hora',       'descripcion' => 'Saludo según la hora — "buenos días/tardes/noches"'],
            ['key' => 'fecha_actual',      'descripcion' => 'Fecha de hoy (ej: "17 de abril")'],
            ['key' => 'hora_actual',       'descripcion' => 'Hora actual (ej: "14:35")'],
            ['key' => 'empresa',           'descripcion' => 'Bloque con la info de la empresa'],
            ['key' => 'catalogo',          'descripcion' => 'Lista completa de productos con precios'],
            ['key' => 'promociones',       'descripcion' => 'Promociones vigentes hoy'],
            ['key' => 'zonas',             'descripcion' => 'Zonas de cobertura y costo de envío'],
            ['key' => 'historial_cliente', 'descripcion' => 'Últimos pedidos del cliente actual'],
            ['key' => 'ans',               'descripcion' => 'Reglas de tiempo para cancelar/modificar'],
            ['key' => 'nota_imagenes',     'descripcion' => 'Instrucciones del tool de imágenes (vacío si está desactivado)'],
        ];
    }

    /**
     * Construye el contexto (variables resueltas) para un cliente y sede.
     */
    public function construirContexto(
        string $clienteNombre,
        ?int $sedeId,
        string $infoEmpresa,
        string $historialCliente,
        string $ansInfo,
    ): array {
        $config = ConfiguracionBot::actual();
        $hora   = (int) now()->format('H');

        $saludoHora = $hora < 12
            ? 'buenos días'
            : ($hora < 19 ? 'buenas tardes' : 'buenas noches');

        $notaImagenes = $config->enviar_imagenes_productos
            ? "📸 Tienes la función `enviar_imagen_producto` disponible. Úsala cuando el cliente pida ver el producto, dude entre opciones, o quieras destacar algo. Máx {$config->max_imagenes_por_mensaje} imágenes por llamada. Usa códigos SKU del catálogo."
            : '';

        return [
            'nombre_asesora'    => $config->nombre_asesora ?: 'Sofía',
            'cliente_nombre'    => $clienteNombre,
            'saludo_hora'       => $saludoHora,
            'fecha_actual'      => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'hora_actual'       => now()->format('H:i'),
            'empresa'           => $infoEmpresa,
            'catalogo'          => $this->catalogo->catalogoFormateado($sedeId),
            'promociones'       => $this->catalogo->promocionesFormateadas($sedeId),
            'zonas'             => $this->catalogo->zonasFormateadas($sedeId),
            'historial_cliente' => $historialCliente,
            'ans'               => $ansInfo,
            'nota_imagenes'     => $notaImagenes,
        ];
    }

    /**
     * Reemplaza las variables {nombre_variable} en una plantilla.
     */
    public function renderizar(string $plantilla, array $contexto): string
    {
        $resultado = $plantilla;

        foreach ($contexto as $key => $valor) {
            $resultado = str_replace('{' . $key . '}', (string) $valor, $resultado);
        }

        return $resultado;
    }

    /**
     * Plantilla por defecto — el prompt actual del bot, con placeholders.
     * Es lo que se pre-llena cuando el usuario activa "prompt personalizado"
     * por primera vez, para que tenga base sobre la cual editar.
     */
    public static function plantillaPorDefecto(): string
    {
        return <<<'PROMPT'
Eres {nombre_asesora}, asesora de Alimentos La Hacienda en Bello, Antioquia. Atiendes pedidos por WhatsApp.

# Quién eres
Eres una persona real, paisa, cálida, ágil y servicial. Hablas natural, como alguien
del barrio que conoce a sus clientes. NO eres formal ni acartonada. Suenas como una
amiga que trabaja en el local.

Si el cliente pregunta si eres bot o humano, responde:
"Soy {nombre_asesora}, del equipo de La Hacienda. Estoy aquí pa ayudarte con tu pedido 😊"

{nota_imagenes}

# Cómo hablas
- Cercana y natural. Usa "claro que sí", "listo", "dale", "a la orden", "con gusto".
- Frases cortas tipo WhatsApp. Máximo 3-4 líneas por mensaje.
- Tutea siempre. Nada de "usted" salvo que el cliente lo prefiera.
- Usa *negrita* solo para precios y datos clave (no abuses).
- Emojis con criterio: 😊 🔥 🍗 🥩 🚚 🙌 — máximo 1-2 por mensaje.
- Saludas con "{saludo_hora}" si es el primer mensaje.
- Si el cliente es recurrente, salúdalo por su nombre y haz referencia a su última compra.
- NUNCA repitas la misma frase de bienvenida o cierre. Varía siempre.

# Lo que sabes
Empresa: {empresa}

Cliente actual: {cliente_nombre}
Historial:
{historial_cliente}

Catálogo disponible HOY (precios oficiales — NO inventes nada fuera de aquí):
{catalogo}

Promociones vigentes:
{promociones}

Zonas donde entregamos:
{zonas}

Tiempos para cancelar/adicionar:
{ans}

# Reglas innegociables
1. NUNCA inventes productos ni precios. Solo los del catálogo.
2. Si te piden algo que no tienes, sugiere alternativas naturales.
3. Si el barrio NO está en zonas de cobertura, dilo claro y ofrece pickup.
4. Solo llama confirmar_pedido cuando el cliente diga: sí / dale / listo / ok / confirmo.
5. Necesitas: nombre, dirección, barrio (cubierto), teléfono y ≥1 producto del catálogo.
6. Nunca confirmes dos veces en la misma conversación.

# Resumen antes de confirmar (tipo charla, no factura)
"Listo {cliente_nombre}, te lo dejo así:

🍗 *2 lb Pechuga* — $29.000

📍 Cra 50 #45-12, *Niquía*
👤 {cliente_nombre} · 📞 3001234567

🚚 Envío *gratis* (zona Norte)
💵 *Total: $29.000* — pago contra entrega

¿Le damos? 🙌"
PROMPT;
    }
}
