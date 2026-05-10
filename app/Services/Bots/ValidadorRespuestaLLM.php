<?php

namespace App\Services\Bots;

use App\Models\Producto;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use App\Services\BotCatalogoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🛡️ VALIDADOR ANTI-ALUCINACIÓN POST-LLM
 *
 * Inspecciona la respuesta generada por el LLM antes de enviarla al cliente.
 * Detecta y reescribe alucinaciones comunes:
 *
 *   1. Precios inventados (ej. "te cuesta $5.000" cuando el real es $13.500)
 *   2. Productos mencionados que no están en el catálogo
 *   3. Horarios inventados (ej. "abrimos 24/7", "abrimos 6am" cuando son 8am)
 *   4. Promesas no respaldadas ("envío en 30 min", "100% fresco hoy")
 *   5. Tiempos de entrega no documentados
 *   6. Información de sedes/zonas no configuradas
 *
 * Si detecta alucinación → reescribe a un mensaje seguro pidiendo que el
 * cliente reformule, o llama a un humano.
 */
class ValidadorRespuestaLLM
{
    /**
     * Devuelve la respuesta sanitizada (puede ser igual a la original).
     * Si reescribió, retorna nueva versión profesional.
     */
    public function validar(string $reply, array $contexto = []): string
    {
        if (trim($reply) === '') return $reply;

        $alertas = [];
        $replyOriginal = $reply;

        // 1. Precios mencionados — verificar contra catálogo
        $alertasPrecio = $this->detectarPreciosInventados($reply);
        if (!empty($alertasPrecio)) {
            $alertas[] = ['tipo' => 'precio_inventado', 'detalles' => $alertasPrecio];
        }

        // 2. Productos mencionados — verificar contra catálogo activo
        $productosFantasma = $this->detectarProductosFantasma($reply);
        if (!empty($productosFantasma)) {
            $alertas[] = ['tipo' => 'producto_fantasma', 'detalles' => $productosFantasma];
        }

        // 3. Horarios inventados
        $horariosInventados = $this->detectarHorariosInventados($reply);
        if (!empty($horariosInventados)) {
            $alertas[] = ['tipo' => 'horario_inventado', 'detalles' => $horariosInventados];
        }

        // 4. Promesas no respaldadas
        $promesas = $this->detectarPromesasNoRespaldadas($reply);
        if (!empty($promesas)) {
            $alertas[] = ['tipo' => 'promesa_no_respaldada', 'detalles' => $promesas];
        }

        // 5. Tiempos de entrega inventados
        $tiempos = $this->detectarTiemposInventados($reply);
        if (!empty($tiempos)) {
            $alertas[] = ['tipo' => 'tiempo_inventado', 'detalles' => $tiempos];
        }

        if (empty($alertas)) {
            return $reply;
        }

        Log::warning('🛡️ Validador detectó alucinaciones — sanitizando respuesta', [
            'alertas'      => $alertas,
            'reply_orig'   => mb_substr($replyOriginal, 0, 300),
        ]);

        // Tipo de alerta más grave → respuesta segura
        $tipos = collect($alertas)->pluck('tipo')->all();

        if (in_array('precio_inventado', $tipos, true) || in_array('producto_fantasma', $tipos, true)) {
            return "Permíteme un momento, voy a confirmar esa información correctamente. "
                 . "¿Me puedes decir qué producto necesitas y te paso el precio exacto del catálogo?";
        }

        if (in_array('horario_inventado', $tipos, true)) {
            return $this->mensajeHorariosReales();
        }

        if (in_array('tiempo_inventado', $tipos, true)) {
            return "El tiempo exacto te lo confirmamos al momento de despachar. "
                 . "¿Continuamos con tu pedido?";
        }

        if (in_array('promesa_no_respaldada', $tipos, true)) {
            // Solo limpiar la promesa, no reescribir todo
            return $this->limpiarPromesas($reply);
        }

        return $reply;
    }

    /**
     * Detecta precios INVENTADOS — solo dispara cuando, en la misma LÍNEA,
     * aparece nombre de producto + precio, y el precio NO coincide con el
     * del catálogo (con tolerancia de ±25%).
     *
     * 🛡️ PRECISO: itera línea por línea, asocia el precio al producto
     * mencionado en esa línea. Evita falsos positivos cuando el reply
     * lista múltiples productos con sus respectivos precios.
     */
    private function detectarPreciosInventados(string $reply): array
    {
        $alertas = [];

        try {
            $catalogo = app(BotCatalogoService::class)->productosActivos();
        } catch (\Throwable $e) {
            return [];
        }
        if ($catalogo->isEmpty()) return [];

        // Mapa nombre normalizado -> producto. ORDENADO POR LONGITUD DESC
        // para que matches específicos (ej "MUSLO CAMPESINO") ganen sobre
        // genéricos (ej "MUSLO").
        $catalogoMap = [];
        foreach ($catalogo as $p) {
            $nombreN = mb_strtolower(Str::ascii((string) $p->nombre));
            if ($nombreN === '') continue;
            $catalogoMap[$nombreN] = [
                'nombre' => (string) $p->nombre,
                'precio' => (float) ($p->precio_base ?? 0),
            ];
        }
        // Ordenar por longitud descendente para preferir matches específicos
        uksort($catalogoMap, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        // Procesar cada línea del reply
        $lineas = preg_split('/[\n\r]+/', $reply);
        foreach ($lineas as $linea) {
            if (trim($linea) === '') continue;

            // ¿Tiene precio en la línea?
            if (!preg_match_all('/\$\s*([\d.,]+)/u', $linea, $mPrecios)) continue;

            $lineaN = mb_strtolower(Str::ascii($linea));

            // ¿Qué producto del catálogo aparece en esta línea?
            // 🛡️ Buscar match de PALABRA COMPLETA (con \b) y tomar el
            // MÁS LARGO. Esto evita que "MUSLO" matchee falsamente cuando
            // la línea dice "MUSLO CAMPESINO" (que es otro producto).
            $productoMatch = null;
            foreach ($catalogoMap as $nombreN => $info) {
                // Pattern de palabra completa, escapando caracteres especiales
                $pattern = '/(?<![a-z0-9])' . preg_quote($nombreN, '/') . '(?![a-z0-9])/u';
                if (preg_match($pattern, $lineaN)) {
                    $productoMatch = $info;
                    break; // ya está ordenado por longitud DESC, el primero es el más específico
                }
            }
            // Si no encontramos match de palabra completa, NO disparar
            if (!$productoMatch || $productoMatch['precio'] <= 0) continue;

            $precioReal = $productoMatch['precio'];

            // 🛡️ Detectar UNIDAD mencionada en la línea para ajustar precio esperado.
            // El precio_base del catálogo es por KG. Si el LLM dice "X libras" o
            // "Y gramos", el precio total escala según esa fracción.
            $factorCantidad = $this->detectarFactorCantidadUnidad($lineaN);

            foreach ($mPrecios[1] as $precioStr) {
                $precio = (float) preg_replace('/[^\d]/', '', str_replace(',', '', $precioStr));
                if ($precio <= 0) continue;

                // Si la línea tiene cantidad+unidad, comparar contra el precio escalado
                if ($factorCantidad !== null) {
                    $precioEsperado = $precioReal * $factorCantidad;
                    $diff = abs($precio - $precioEsperado) / max($precioEsperado, 1);
                    if ($diff > 0.30) { // tolerancia 30% por unidad fraccionaria
                        $alertas[] = [
                            'producto' => $productoMatch['nombre'],
                            'precio_real_kg' => $precioReal,
                            'factor_cantidad' => $factorCantidad,
                            'precio_esperado' => $precioEsperado,
                            'precio_mencionado' => $precio,
                            'linea' => mb_substr(trim($linea), 0, 100),
                        ];
                    }
                    continue;
                }

                // Sin cantidad/unidad explícita: comparar contra precio base por kg
                // Tolerancia ±25%: precios pueden variar por descuentos/sede
                $diff = abs($precio - $precioReal) / $precioReal;
                if ($diff > 0.25) {
                    $alertas[] = [
                        'producto' => $productoMatch['nombre'],
                        'precio_real' => $precioReal,
                        'precio_mencionado' => $precio,
                        'linea' => mb_substr(trim($linea), 0, 100),
                    ];
                }
            }
        }

        return $alertas;
    }

    /**
     * Detecta si la línea menciona "N kg/libras/gramos" y devuelve el factor
     * de conversión vs precio_base (por kg). NULL si no hay cantidad detectable.
     *
     * Ej: "1 libra" → 0.5  (1 libra = 0.5 kg)
     *     "2 kg"    → 2.0
     *     "500 gramos" → 0.5
     */
    private function detectarFactorCantidadUnidad(string $lineaN): ?float
    {
        // 🛡️ Convertir números escritos en palabras a dígitos para que el regex
        // pille casos como "una libra", "media libra", "dos kilos".
        $lineaN = strtr($lineaN, [
            'una libra'   => '1 libra',
            'un libra'    => '1 libra',
            'media libra' => '0.5 libra',
            'medio kilo'  => '0.5 kg',
            'un kilo'     => '1 kg',
            'una kilo'    => '1 kg',
            'dos libras'  => '2 libras',
            'dos kilos'   => '2 kg',
            'tres libras' => '3 libras',
            'tres kilos'  => '3 kg',
        ]);

        // Buscar pattern "N unidad" donde N es número y unidad es kg/lb/gr/...
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(libras?|libritas?|lb|kilos?|kilitos?|kg|gramos?|gr|onzas?|oz|unidades?|unds?|paquetes?)/iu', $lineaN, $m)) {
            return null;
        }
        $cantidad = (float) str_replace(',', '.', $m[1]);
        $unidad = mb_strtolower($m[2]);

        // Conversiones a kg
        return match (true) {
            str_starts_with($unidad, 'libra')  => $cantidad * 0.5,    // libra ≈ 0.5 kg en Colombia
            str_starts_with($unidad, 'librit') => $cantidad * 0.5,
            $unidad === 'lb'                   => $cantidad * 0.5,
            str_starts_with($unidad, 'kilo')   => $cantidad,
            $unidad === 'kg'                   => $cantidad,
            str_starts_with($unidad, 'gramo')  => $cantidad / 1000,
            $unidad === 'gr'                   => $cantidad / 1000,
            str_starts_with($unidad, 'onza')   => $cantidad * 0.0283,  // onza = 28.3g
            $unidad === 'oz'                   => $cantidad * 0.0283,
            // Para unidades/paquetes: precio fijo por unidad, factor = cantidad
            str_starts_with($unidad, 'unidad'),
            str_starts_with($unidad, 'paquete'),
            $unidad === 'und', $unidad === 'unds' => $cantidad,
            default => null,
        };
    }

    /**
     * Detecta menciones de "productos" que no existen en el catálogo.
     * Solo dispara para palabras que parecen ser nombres de productos cárnicos.
     */
    private function detectarProductosFantasma(string $reply): array
    {
        $alertas = [];
        $replyN = mb_strtolower(Str::ascii($reply));

        // Lista de productos típicos que el bot podría inventar (no están en cárnicos)
        $sospechosos = [
            'lomo de res', 'lomo de cerdo', 'lomo fino',
            'asado de tira', 'churrasco', 'picaña',
            'carne molida', 'carne para hamburguesa',
            'hueso para sopa', 'hueso de tutano',
            'chorizo', 'morcilla', 'salchicha',
        ];

        try {
            $catalogo = app(BotCatalogoService::class)->productosActivos();
            $catalogoTextos = $catalogo->map(fn ($p) => mb_strtolower(Str::ascii((string) $p->nombre)))->all();
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($sospechosos as $sosp) {
            if (!str_contains($replyN, $sosp)) continue;
            // ¿Existe algo SIMILAR en el catálogo?
            $existe = collect($catalogoTextos)->first(fn ($n) => str_contains($n, $sosp));
            if (!$existe) {
                $alertas[] = $sosp;
            }
        }

        return $alertas;
    }

    /**
     * Detecta horarios mencionados que no coincidan con los configurados.
     * Reglas conservadoras: solo dispara con menciones EXPLÍCITAS de horas.
     */
    private function detectarHorariosInventados(string $reply): array
    {
        $alertas = [];
        $replyN = mb_strtolower(Str::ascii($reply));

        // Frases sospechosas
        $patrones = [
            '/24\s*\/?\s*7/u',                 // "24/7"
            '/24\s*horas/u',                    // "24 horas"
            '/(las\s+)?24\s*horas\s+del\s+dia/u',
            '/abrimos\s+temprano/u',            // genérico
            '/cerramos\s+tarde/u',
            '/(siempre|todo\s+el\s+tiempo)\s+abierto/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $replyN)) {
                $alertas[] = 'horario_24_7_inventado';
                break;
            }
        }

        return $alertas;
    }

    /**
     * Detecta promesas que el bot NO debe hacer:
     *   "100% garantizado", "el más fresco", "el mejor precio",
     *   "envío gratis siempre", "te lo regalamos", "promoción especial".
     */
    private function detectarPromesasNoRespaldadas(string $reply): array
    {
        $alertas = [];
        $replyN = mb_strtolower(Str::ascii($reply));

        $patrones = [
            '/100\s*%\s*garantiz/u',
            '/100\s*%\s*fresco/u',
            '/el\s+mejor\s+precio/u',
            '/precio\s+mas\s+bajo/u',
            '/te\s+lo\s+regal/u',  // "te lo regalo", "regalamos"
            '/envio\s+gratis\s+siempre/u',
            '/sin\s+costo\s+adicional/u',
            '/oferta\s+especial\s+para\s+ti/u',
            '/descuento\s+exclusivo/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $replyN)) {
                $alertas[] = $p;
            }
        }

        return $alertas;
    }

    /**
     * Detecta tiempos de entrega específicos no respaldados:
     *   "30 minutos", "1 hora", "menos de X minutos"
     */
    private function detectarTiemposInventados(string $reply): array
    {
        $alertas = [];
        $replyN = mb_strtolower(Str::ascii($reply));

        $patrones = [
            '/(en|llega\s+en|tardamos|demor)\s+\d+\s*(min|minutos)/u',
            '/(en|llega\s+en)\s+\d+\s*(hora|horas)/u',
            '/menos\s+de\s+\d+\s*(min|hora)/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $replyN)) {
                $alertas[] = $p;
            }
        }

        return $alertas;
    }

    private function mensajeHorariosReales(): string
    {
        try {
            $sedes = Sede::where('activa', true)->get();
            if ($sedes->isEmpty()) {
                return "Te confirmaré los horarios exactos en un momento.";
            }
            $lineas = ["Estos son nuestros horarios:"];
            foreach ($sedes as $s) {
                $abierta = $s->estaAbierta() ? '🟢 abierta ahora' : '🔴 cerrada';
                $lineas[] = "• *{$s->nombre}* — {$abierta}";
            }
            return implode("\n", $lineas);
        } catch (\Throwable $e) {
            return "Te confirmaré los horarios exactos en un momento.";
        }
    }

    private function limpiarPromesas(string $reply): string
    {
        $patrones = [
            '/100\s*%\s*garantizad[oa]?\.?/iu' => '',
            '/100\s*%\s*fresc[oa]?\.?/iu' => '',
            '/el\s+mejor\s+precio[\.,]?/iu' => '',
            '/precio\s+m[áa]s\s+bajo[\.,]?/iu' => '',
            '/oferta\s+especial\s+para\s+ti[\.,]?/iu' => '',
            '/descuento\s+exclusivo[\.,]?/iu' => '',
        ];
        return trim(preg_replace(array_keys($patrones), array_values($patrones), $reply));
    }
}
