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
                    ->where('activo', true)
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
     * Resetea el estado (al saludar tras inactividad, o tras confirmar pedido nuevo).
     */
    public function resetear(ConversacionWhatsapp $conv, ?string $motivo = null): void
    {
        $estado = $this->obtener($conv);
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
            'cedula'             => null,
            'nombre_cliente'     => null,
            'email'              => null,
            'cliente_existe_erp' => false,
            'datos_erp'          => null,
            'metodo_pago'        => null,
            'cupon_code'         => null,
            'notas'              => null,
            'validaciones'       => null,
            'pedido_id'          => null,
            'confirmado_at'      => null,
            'abandonado_at'      => $motivo ? now() : null,
            'motivo_abandono'    => $motivo,
        ])->save();

        Log::info('🔄 Estado pedido reseteado', [
            'conv_id' => $conv->id,
            'motivo'  => $motivo,
        ]);
    }

    /**
     * Lógica simple de avance de paso basada en datos disponibles.
     * NO usa LLM. Es una máquina de estados determinista.
     */
    public function avanzarPaso(ConversacionPedidoEstado $estado): void
    {
        $nuevo = $estado->paso_actual;

        if (empty($estado->productos)) {
            $nuevo = ConversacionPedidoEstado::PASO_PRODUCTO;
        } elseif (empty($estado->metodo_entrega) ||
                  ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_DOMICILIO && !$estado->cobertura_validada) ||
                  ($estado->metodo_entrega === ConversacionPedidoEstado::METODO_RECOGER && empty($estado->sede_id))) {
            $nuevo = ConversacionPedidoEstado::PASO_ENTREGA;
        } elseif (empty($estado->cedula) && empty($estado->nombre_cliente)) {
            $nuevo = ConversacionPedidoEstado::PASO_IDENTIFICACION;
        } elseif ($estado->estaCompleto() && !$estado->confirmado_at) {
            $nuevo = ConversacionPedidoEstado::PASO_CONFIRMACION;
        }

        if ($nuevo !== $estado->paso_actual && $estado->paso_actual !== ConversacionPedidoEstado::PASO_CONFIRMADO) {
            $estado->paso_actual = $nuevo;
            $estado->save();
        }
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

        $partes = ["📋 ESTADO ACTUAL DEL PEDIDO (BD — fuente de verdad):"];
        $partes[] = "  • Paso actual: {$estado->paso_actual}";

        if (!empty($estado->productos)) {
            $prods = collect($estado->productos)->map(fn ($p) =>
                ($p['quantity'] ?? 1) . ' ' . ($p['unit'] ?? '') . ' ' . ($p['name'] ?? '')
            )->implode(', ');
            $partes[] = "  • Productos: {$prods}";
        }

        if ($estado->metodo_entrega) {
            $partes[] = "  • Entrega: {$estado->metodo_entrega}";
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
