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
            ['key' => 'cliente_nombre',         'descripcion' => 'Nombre completo del cliente que está chateando'],
            ['key' => 'cliente_primer_nombre',  'descripcion' => 'Solo el primer nombre del cliente (más natural en WhatsApp)'],
            ['key' => 'cliente_es_conocido',    'descripcion' => 'SI/NO — si ya tenemos el nombre real del cliente en BD'],
            ['key' => 'horarios_sedes',         'descripcion' => 'Horarios completos de todas las sedes activas'],
            ['key' => 'sede_estado_actual',     'descripcion' => 'Estado de la sede ahora: ABIERTA o CERRADA + horario de hoy'],
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
        // Forzar timezone Bogotá para que coincida con Sede::estaAbierta()
        $ahora  = \Carbon\Carbon::now('America/Bogota');
        $hora   = (int) $ahora->format('H');

        $saludoHora = $hora < 12
            ? 'buenos días'
            : ($hora < 19 ? 'buenas tardes' : 'buenas noches');

        $notaImagenes = $config->enviar_imagenes_productos
            ? "📸 Tienes la función `enviar_imagen_producto` disponible. Úsala cuando el cliente pida ver el producto, dude entre opciones, o quieras destacar algo. Máx {$config->max_imagenes_por_mensaje} imágenes por llamada. Usa códigos SKU del catálogo."
            : '';

        // Calcular primer nombre (más natural para WhatsApp informal)
        $nombreLimpio = trim($clienteNombre);
        $primerNombre = '';
        $clienteConocido = false;

        if ($nombreLimpio !== '' && !in_array(mb_strtolower($nombreLimpio), ['cliente', 'usuario', 'sin nombre', ''], true)) {
            $partes = preg_split('/\s+/', $nombreLimpio);
            $primerNombre = ucfirst(mb_strtolower($partes[0] ?? ''));
            $clienteConocido = true;
        }

        // Horarios de las sedes
        $sedes = \App\Models\Sede::where('activa', true)->get();
        $horariosTexto = $sedes->map(fn ($s) => $s->horariosFormateadosTexto())->implode("\n\n");
        if ($horariosTexto === '') {
            $horariosTexto = '(No hay sedes con horarios configurados)';
        }

        // Estado actual de la primera sede activa
        $sedeRef = $sedeId
            ? \App\Models\Sede::find($sedeId)
            : $sedes->first();

        $estadoSedeTexto = '(Sin sede de referencia)';
        if ($sedeRef) {
            $estado = $sedeRef->estaAbierta() ? '🟢 ABIERTA AHORA' : '🔴 CERRADA AHORA';
            $estadoSedeTexto = "{$sedeRef->nombre}: {$estado}. " . $sedeRef->horarioHoyTexto();
            if (!$sedeRef->estaAbierta() && $sedeRef->mensaje_cerrado) {
                $estadoSedeTexto .= "\nMensaje: {$sedeRef->mensaje_cerrado}";
            }
        }

        return [
            'nombre_asesora'        => $config->nombre_asesora ?: 'Sofía',
            'cliente_nombre'        => $clienteNombre,
            'cliente_primer_nombre' => $primerNombre,
            'cliente_es_conocido'   => $clienteConocido ? 'SI' : 'NO',
            'saludo_hora'           => $saludoHora,
            'horarios_sedes'        => $horariosTexto,
            'sede_estado_actual'    => $estadoSedeTexto,
            'fecha_actual'      => $ahora->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'hora_actual'       => $ahora->format('h:i a'),  // 12h con AM/PM
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
     * Plantilla por defecto — prompt profesional completo, paisa, listo para producción.
     * Es lo que se pre-llena cuando el usuario activa "prompt personalizado".
     */
    public static function plantillaPorDefecto(): string
    {
        return <<<'PROMPT'
# IDENTIDAD

Eres **{nombre_asesora}**, asesora comercial.
Hoy es {fecha_actual} ({hora_actual}).

Atiendes mensajes por WhatsApp. Eres una persona real, NO un bot.

═══════════════════════════════════════════════════════════════════════════════
# CONTEXTO

**Empresa:**
{empresa}

**Cliente actual:** {cliente_nombre} · ¿Conocido?: {cliente_es_conocido} · Primer nombre: {cliente_primer_nombre}

**Historial:**
{historial_cliente}

═══════════════════════════════════════════════════════════════════════════════
# 📦 CATÁLOGO (única fuente de verdad)

{catalogo}

═══════════════════════════════════════════════════════════════════════════════
# 🎁 PROMOCIONES

{promociones}

═══════════════════════════════════════════════════════════════════════════════
# 🏪 HORARIOS

Estado: {sede_estado_actual}
{horarios_sedes}

═══════════════════════════════════════════════════════════════════════════════
# 🗺️ ZONAS DE COBERTURA

{zonas}

═══════════════════════════════════════════════════════════════════════════════
# ⏱️ ANS

{ans}

═══════════════════════════════════════════════════════════════════════════════
{nota_imagenes}

═══════════════════════════════════════════════════════════════════════════════
# REGLAS BÁSICAS

1. Solo ofreces productos del CATÁLOGO. Nunca inventes.
2. Si no está en el catálogo, dilo claro.
3. Antes de confirmar pedido, llama `validar_cobertura` y obtén nombre, teléfono, dirección.
4. Confirma solo con `sí` / `dale` / `listo` / `confirmo` explícito.
5. Llama `confirmar_pedido` solo cuando todo esté validado.

⚠️ Esta es una plantilla MÍNIMA. Ve a /configuracion/bot → "Prompt personalizado del bot"
y escribe el comportamiento completo que se ajuste a tu negocio.
PROMPT;
    }
}
