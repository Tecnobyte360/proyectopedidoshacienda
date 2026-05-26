#!/usr/bin/env php
<?php
/**
 * AGI: registrar_llamada.php
 *
 * Asterisk lo invoca al recibir cada llamada entrante.
 * Notifica a Kivox para que registre el inicio de llamada.
 *
 * Args:
 *   $1 = caller_id (+57320...)
 *   $2 = did       (número Twilio que recibió la llamada)
 *   $3 = unique_id (ID único de la llamada en Asterisk)
 */
require __DIR__ . '/agi_helpers.php';

$callerId = $argv[1] ?? 'desconocido';
$did      = $argv[2] ?? '';
$uniqueId = $argv[3] ?? '';

$payload = [
    'caller_id' => $callerId,
    'did'       => $did,
    'unique_id' => $uniqueId,
    'evento'    => 'inbound_start',
    'timestamp' => date('c'),
];

agi_log("📞 Llamada entrante: from={$callerId} did={$did} uid={$uniqueId}");

$resp = kivox_api_post('/ivr/llamadas/registrar', $payload);

if ($resp && isset($resp->llamada_id)) {
    // Guardar el ID en una variable de canal para usarlo después
    agi_verbose("Llamada registrada en Kivox: ID={$resp->llamada_id}");
    echo "SET VARIABLE KIVOX_LLAMADA_ID {$resp->llamada_id}\n";
    fgets(STDIN);
}

exit(0);
