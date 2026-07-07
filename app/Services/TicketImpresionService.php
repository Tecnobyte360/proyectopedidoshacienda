<?php

namespace App\Services;

use App\Models\Impresora;
use App\Models\Pedido;

/**
 * 🧾 Arma el TEXTO de los tickets que se envían a la impresora térmica.
 * El agente le agrega los comandos ESC/POS (init/corte); aquí va el contenido.
 * Ancho típico: 42 caracteres (impresora de 80mm).
 */
class TicketImpresionService
{
    private const ANCHO = 42;      // ancho normal (letra alta, sin doble ancho)
    private const ANCHO_G = 21;     // ancho cuando la letra es doble (alto+ancho)

    // Comandos ESC/POS de tamaño / estilo
    private const NEGRITA_ON  = "\x1B\x45\x01";
    private const NEGRITA_OFF = "\x1B\x45\x00";
    private const T_NORMAL    = "\x1D\x21\x00"; // tamaño normal
    private const T_ALTO      = "\x1D\x21\x01"; // DOBLE ALTO (letra grande, ancho normal)
    private const T_GRANDE    = "\x1D\x21\x11"; // DOBLE ALTO + ANCHO (extra grande)

    public function ticketPrueba(Impresora $imp): string
    {
        $out  = self::NEGRITA_ON;
        $out .= self::T_GRANDE . $this->centrar('KIVOX', self::ANCHO_G) . "\n";
        $out .= self::T_ALTO;
        $out .= $this->centrar('PRUEBA DE IMPRESION', self::ANCHO) . "\n";
        $out .= str_repeat('-', self::ANCHO) . "\n";
        $out .= 'Impresora: ' . mb_substr($imp->nombre, 0, 30) . "\n";
        $out .= 'PC: ' . ($imp->pc_nombre ?: '-') . "\n";
        $out .= 'Fecha: ' . now('America/Bogota')->format('d/m/Y h:i a') . "\n";
        $out .= str_repeat('-', self::ANCHO) . "\n";
        $out .= $this->centrar('Conexion Kivox -> Impresora', self::ANCHO) . "\n";
        $out .= $this->centrar('FUNCIONA! :)', self::ANCHO) . "\n";
        $out .= self::T_NORMAL . self::NEGRITA_OFF;
        return $out;
    }

    /**
     * 🧾 Ticket / comanda de un pedido con letra grande.
     */
    public function ticketPedido(Pedido $pedido): string
    {
        $out  = self::NEGRITA_ON;
        // Encabezado EXTRA grande con el número de pedido
        $out .= self::T_GRANDE . $this->centrar('PEDIDO #' . $pedido->id, self::ANCHO_G) . "\n";

        // Cuerpo en DOBLE ALTO (letra grande, ancho normal = 42 caracteres)
        $out .= self::T_ALTO;
        $out .= str_repeat('-', self::ANCHO) . "\n";
        $out .= 'Cliente: ' . mb_substr((string) $pedido->cliente_nombre, 0, 32) . "\n";
        if ($pedido->telefono_contacto || $pedido->telefono) {
            $out .= 'Tel: ' . ($pedido->telefono_contacto ?: $pedido->telefono) . "\n";
        }
        $entrega = $pedido->esRecogerEnSede() ? 'RECOGE EN SEDE' : 'DOMICILIO';
        $out .= 'Entrega: ' . $entrega . "\n";
        if (!$pedido->esRecogerEnSede() && $pedido->direccion) {
            $out .= 'Dir: ' . mb_substr((string) $pedido->direccion, 0, 36) . "\n";
            if ($pedido->barrio) $out .= 'Barrio: ' . mb_substr((string) $pedido->barrio, 0, 34) . "\n";
        }
        $out .= str_repeat('-', self::ANCHO) . "\n";
        foreach ($pedido->detalles as $d) {
            $cant = rtrim(rtrim(number_format((float) $d->cantidad, 2, '.', ''), '0'), '.');
            $out .= $cant . ' x ' . mb_substr((string) $d->producto, 0, 34) . "\n";
        }
        $out .= str_repeat('-', self::ANCHO) . "\n";
        $out .= 'Fecha: ' . optional($pedido->created_at)->format('d/m/Y h:i a') . "\n";
        $out .= self::T_NORMAL . self::NEGRITA_OFF;
        return $out;
    }

    /**
     * 🖨️ Encola la comanda del pedido para la impresora del tenant.
     * Si el tenant no tiene impresora activa, no hace nada (silencioso).
     */
    public function encolarComanda(Pedido $pedido): bool
    {
        $imp = \App\Models\Impresora::where('tenant_id', $pedido->tenant_id)
            ->where('activa', true)
            ->first();
        if (!$imp) return false;

        // 🛡️ ANTI-DUPLICADO: si la persona da clic varias veces, NO encolar la
        //    misma comanda de nuevo. Se bloquea si hay una comanda de este pedido
        //    pendiente/enviada (aún no impresa) o impresa en los últimos 60s.
        $duplicado = \App\Models\TrabajoImpresion::where('impresora_id', $imp->id)
            ->where('pedido_id', $pedido->id)
            ->where('tipo', 'ticket')
            ->where(function ($q) {
                $q->whereIn('estado', [
                        \App\Models\TrabajoImpresion::ESTADO_PENDIENTE,
                        \App\Models\TrabajoImpresion::ESTADO_ENVIADO,
                    ])
                  ->orWhere('created_at', '>=', now()->subSeconds(60));
            })
            ->exists();
        if ($duplicado) {
            return false;
        }

        \App\Models\TrabajoImpresion::create([
            'tenant_id'    => $pedido->tenant_id,
            'impresora_id' => $imp->id,
            'pedido_id'    => $pedido->id,
            'tipo'         => 'ticket',
            'contenido'    => $this->ticketPedido($pedido->loadMissing('detalles')),
            'estado'       => \App\Models\TrabajoImpresion::ESTADO_PENDIENTE,
        ]);

        return true;
    }

    private function centrar(string $t, ?int $ancho = null): string
    {
        $ancho = $ancho ?: self::ANCHO;
        $t = mb_substr($t, 0, $ancho);
        $pad = (int) max(0, floor(($ancho - mb_strlen($t)) / 2));
        return str_repeat(' ', $pad) . $t;
    }
}
