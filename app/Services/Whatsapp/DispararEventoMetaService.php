<?php

namespace App\Services\Whatsapp;

use App\Models\MetaWhatsappDisparador;
use App\Models\MetaWhatsappPlantilla;
use App\Models\Pedido;
use App\Models\Tenant;
use App\Services\Meta\MetaWhatsappCloudService;
use App\Services\WhatsappSenderService;
use Illuminate\Support\Facades\Log;

/**
 * Dispara plantillas Meta cuando ocurren eventos del sistema (cambio de
 * estado de pedido, cumpleaños, etc).
 *
 * Si el tenant no usa Meta (es TecnoByteApp), construye un texto plano
 * a partir del body_preview de la plantilla y lo envía como texto libre
 * (mejor que no enviar nada).
 */
class DispararEventoMetaService
{
    public function __construct(
        private MetaWhatsappCloudService $meta,
        private WhatsappSenderService $senderLegacy,
    ) {}

    /**
     * Dispara el evento para un cliente concreto. Resuelve plantilla activa
     * del tenant que matchee el `evento`, llena variables con `vars` y envía.
     *
     * @param string $evento p.ej. 'pedido_confirmado', 'pedido_entregado'
     * @param string $telefono E.164 sin '+'
     * @param array  $vars   ['nombre'=>'Stiven', 'numero'=>18, 'total'=>'94.650']
     * @param int|null $tenantId  si null, usa tenant actual
     */
    public function disparar(string $evento, string $telefono, array $vars = [], ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?: app(\App\Services\TenantManager::class)->id();
        if (!$tenantId) {
            Log::warning("📢 Disparador sin tenant_id, evento={$evento}");
            return false;
        }

        $disparador = MetaWhatsappDisparador::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('evento', $evento)
            ->where('activo', true)
            ->with('plantilla')
            ->first();

        if (!$disparador || !$disparador->plantilla) {
            Log::info("📢 Sin disparador configurado", ['evento' => $evento, 'tenant_id' => $tenantId]);
            return false;
        }

        $plantilla = $disparador->plantilla;
        $varsOrdenadas = $this->mapearVariables($disparador->variables_map ?? [], $vars, $plantilla->num_variables ?? 0);

        $tenant = Tenant::find($tenantId);
        $provider = $tenant?->proveedorWhatsappResuelto() ?? Tenant::WA_PROVIDER_TECNOBYTE;

        if ($provider === Tenant::WA_PROVIDER_META) {
            $ok = $this->meta->enviarPlantilla(
                $telefono,
                $plantilla->nombre,
                $varsOrdenadas,
                $tenantId,
                $plantilla->idioma ?: 'es'
            );
            Log::info("📢 Evento Meta disparado", [
                'evento' => $evento, 'plantilla' => $plantilla->nombre,
                'to' => $telefono, 'ok' => $ok,
            ]);
            return $ok;
        }

        // Provider legacy (TecnoByteApp): renderizar body_preview con vars y enviar texto
        $texto = $this->renderBodyComoTexto($plantilla->body_preview, $varsOrdenadas);
        $ok = $this->senderLegacy->enviarTexto($telefono, $texto);
        Log::info("📢 Evento legacy disparado (TecnoByte)", [
            'evento' => $evento, 'to' => $telefono, 'ok' => $ok,
        ]);
        return $ok;
    }

    /**
     * Helper específico para pedidos. Resuelve cliente, teléfono y vars
     * comunes del pedido automáticamente.
     */
    public function dispararParaPedido(string $evento, Pedido $pedido): bool
    {
        $cliente = $pedido->cliente;
        if (!$cliente?->telefono_normalizado) {
            Log::info("📢 Pedido sin teléfono cliente, evento omitido", [
                'pedido_id' => $pedido->id, 'evento' => $evento,
            ]);
            return false;
        }

        $vars = [
            'nombre'        => $cliente->nombre ?: 'Cliente',
            'numero'        => (string) $pedido->id,
            'total'         => number_format((float) $pedido->total, 0, ',', '.'),
            'estado'        => $pedido->estado,
            'observacion'   => (string) ($pedido->observacion_estado ?: ''),
            'domiciliario'  => $pedido->domiciliario?->nombre ?: '',
            'eta_minutos'   => (string) ($pedido->tiempo_estimado_min ?? 30),
        ];

        return $this->disparar($evento, $cliente->telefono_normalizado, $vars, $pedido->tenant_id);
    }

    /**
     * Convierte el array asociativo de variables (`['nombre'=>'X']`) en la
     * lista posicional que requiere enviarPlantilla (`['X','...','...']`).
     *
     * `$variablesMap` viene del disparador con el formato:
     *   ['1' => 'nombre', '2' => 'numero', '3' => 'total']
     * indicando qué variable del usuario va en cada {{N}} de la plantilla.
     */
    private function mapearVariables(array $variablesMap, array $vars, int $numVariables): array
    {
        $ordenadas = [];
        for ($i = 1; $i <= max($numVariables, count($variablesMap)); $i++) {
            $clave = $variablesMap[(string) $i] ?? $variablesMap[$i] ?? null;
            $ordenadas[] = $clave ? (string) ($vars[$clave] ?? '') : '';
        }
        return $ordenadas;
    }

    /**
     * Sustituye {{1}}, {{2}}, ... en el body por las variables.
     */
    private function renderBodyComoTexto(?string $body, array $varsOrdenadas): string
    {
        if (!$body) return '';
        $texto = $body;
        foreach ($varsOrdenadas as $i => $valor) {
            $idx = $i + 1;
            $texto = str_replace(['{{' . $idx . '}}', '{{ ' . $idx . ' }}'], (string) $valor, $texto);
        }
        return $texto;
    }
}
