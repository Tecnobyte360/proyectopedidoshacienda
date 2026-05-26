#!/usr/bin/env php
<?php
/**
 * AGI: consultar_pedido.php
 *
 * Consulta a Kivox el último pedido del número que llama y
 * reproduce el resultado por TTS.
 *
 * Args:
 *   $1 = caller_id (+57320...)
 */
require __DIR__ . '/agi_helpers.php';

$callerId = $argv[1] ?? '';
$telefono = normalizar_telefono($callerId);

agi_log("🔎 Consultando pedido para {$telefono}");

$resp = kivox_api_get("/ivr/pedido-por-telefono/{$telefono}");

if (!$resp || empty($resp->encontrado)) {
    agi_say("No encontramos pedidos asociados a tu número. Si crees que es un error, te transferimos con un asesor marcando tres en el menú principal.");
    exit(0);
}

$p = $resp->pedido;
$texto = "Tu pedido número {$p->id} se encuentra {$p->estado_humano}. "
       . ($p->minutos_estimados
           ? "Llega en aproximadamente {$p->minutos_estimados} minutos. "
           : "")
       . ($p->domiciliario_nombre
           ? "Tu domiciliario es {$p->domiciliario_nombre}. "
           : "");

agi_say($texto);

// Notificar a Kivox que esta llamada consultó un pedido
kivox_api_post('/ivr/llamadas/evento', [
    'unique_id'  => getenv('AGI_UNIQUEID'),
    'evento'     => 'consulta_pedido',
    'pedido_id'  => $p->id,
    'caller_id'  => $callerId,
]);

exit(0);
