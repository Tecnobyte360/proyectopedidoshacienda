<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Motor de chatbot por MENÚ DETERMINISTA (sin IA).
 *
 * El cliente navega marcando NÚMEROS. Cada respuesta es FIJA y exacta
 * (ideal para bancos / instituciones donde no se permite alucinar).
 *
 * El árbol del menú se define como un array (columna `menu_json` del tenant):
 *   [
 *     'welcome' => [
 *        'text'    => 'Bienvenido...\n1 - ...\n2 - ...',
 *        'options' => ['1' => 'nodoA', '2' => 'nodoB'],
 *     ],
 *     'nodes' => [
 *        'nodoA' => ['text' => 'Menú A\n1 - ...', 'options' => ['1'=>'a1'], 'back' => 'welcome'],
 *        'a1'    => ['text' => 'Respuesta fija exacta', 'back' => 'nodoA'],  // hoja (sin options)
 *        ...
 *     ],
 *   ]
 *
 * Reglas:
 *   - Primer contacto / saludo / estado perdido → muestra 'welcome'.
 *   - Marcar "0" → vuelve al nodo 'back' (menú padre). Por defecto 'welcome'.
 *   - Una hoja (nodo sin 'options') muestra su texto + pie "Marque 0 para volver…".
 *   - Si el cliente marca un número inválido → reprompt del menú actual.
 *   - Estado por conversación en caché (12h), no requiere columnas extra.
 */
class MenuDeterministaService
{
    private const PIE_VOLVER = "\n\n↩️ Marque *0* para volver al menú principal.";

    private const SALUDOS = [
        'hola', 'holaa', 'ola', 'buenas', 'buenos', 'buen', 'menu', 'menú',
        'inicio', 'start', 'hi', 'hello', 'epa', 'saludos', 'buenas tardes',
        'buenos dias', 'buenos días', 'buenas noches',
    ];

    /**
     * Procesa el mensaje entrante y devuelve la respuesta determinista.
     *
     * @param array  $menu     Árbol del menú (welcome + nodes).
     * @param int|string $tenantId
     * @param string $telefono Teléfono normalizado del cliente.
     * @param string $texto    Mensaje crudo del cliente.
     */
    public function responder(array $menu, $tenantId, string $telefono, string $texto): string
    {
        $welcome = $menu['welcome'] ?? null;
        $nodes   = $menu['nodes'] ?? [];

        if (!$welcome || empty($welcome['text'])) {
            Log::warning('MenuDeterminista: menú sin welcome válido', ['tenant' => $tenantId]);
            return 'En este momento el servicio no está disponible. Intenta más tarde.';
        }

        $cacheKey = "wa_menu_t{$tenantId}_{$telefono}";
        $estado   = Cache::get($cacheKey);   // id del nodo donde está parado el cliente

        $limpio = trim(mb_strtolower($texto));
        $digito = $this->extraerDigito($texto);

        // ── 1) Primer contacto, saludo, o estado perdido → WELCOME ──
        if ($estado === null || $this->esSaludo($limpio)) {
            Cache::put($cacheKey, 'welcome', now()->addHours(12));
            return $welcome['text'];
        }

        // ── Nodo actual ──
        $nodoActual = $estado === 'welcome'
            ? $welcome
            : ($nodes[$estado] ?? $welcome);

        // ── 2) "0" → volver al menú padre ──
        if ($limpio === '0') {
            $destino = $nodoActual['back'] ?? 'welcome';
            return $this->irANodo($menu, $cacheKey, $destino);
        }

        // ── 3) Opciones aplicables: las del nodo actual, o si es hoja, las del padre ──
        $opciones = $nodoActual['options'] ?? null;
        if ($opciones === null) {
            // hoja: permitir saltar a otra opción del menú padre sin marcar 0 antes
            $padreId = $nodoActual['back'] ?? 'welcome';
            $padre   = $padreId === 'welcome' ? $welcome : ($nodes[$padreId] ?? null);
            $opciones = $padre['options'] ?? null;
        }

        // ── 4) Match por número ──
        if ($digito !== null && $opciones && isset($opciones[$digito])) {
            return $this->irANodo($menu, $cacheKey, $opciones[$digito]);
        }

        // ── 5) Sin match → reprompt del menú vigente ──
        $menuVigente = ($nodoActual['options'] ?? null) !== null
            ? $nodoActual
            : (($nodoActual['back'] ?? 'welcome') === 'welcome'
                ? $welcome
                : ($nodes[$nodoActual['back']] ?? $welcome));

        return "No entendí esa opción 🙏. Por favor responde *solo con el número* de la opción que deseas:\n\n"
            . ($menuVigente['text'] ?? $welcome['text']);
    }

    /** Mueve el estado al nodo destino y renderiza su contenido. */
    private function irANodo(array $menu, string $cacheKey, string $destinoId): string
    {
        $welcome = $menu['welcome'];
        $nodes   = $menu['nodes'] ?? [];

        Cache::put($cacheKey, $destinoId, now()->addHours(12));

        if ($destinoId === 'welcome') {
            return $welcome['text'];
        }

        $nodo = $nodes[$destinoId] ?? null;
        if (!$nodo) {
            Cache::put($cacheKey, 'welcome', now()->addHours(12));
            return $welcome['text'];
        }

        $texto = (string) ($nodo['text'] ?? '');

        // Si es hoja (sin opciones), agregar el pie para volver.
        if (($nodo['options'] ?? null) === null) {
            $texto .= self::PIE_VOLVER;
        }

        return $texto;
    }

    private function esSaludo(string $limpio): bool
    {
        if ($limpio === '') return false;
        // Coincidencia exacta o que empiece por un saludo conocido.
        foreach (self::SALUDOS as $s) {
            if ($limpio === $s || str_starts_with($limpio, $s . ' ')) {
                return true;
            }
        }
        return false;
    }

    /** Extrae el primer dígito/numero del texto (ej "opcion 2" → "2"). */
    private function extraerDigito(string $texto): ?string
    {
        if (preg_match('/\d{1,2}/', $texto, $m)) {
            return ltrim($m[0], '0') === '' ? '0' : ltrim($m[0], '0');
        }
        return null;
    }
}
