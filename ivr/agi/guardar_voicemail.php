#!/usr/bin/env php
<?php
/**
 * AGI: guardar_voicemail.php
 *
 * Cuando el asesor no contesta, grabamos un mensaje del cliente
 * y notificamos al tenant por correo/WhatsApp para devolverle la llamada.
 *
 * Args:
 *   $1 = caller_id
 */
require __DIR__ . '/agi_helpers.php';

$callerId = $argv[1] ?? '';
$filename = '/var/spool/asterisk/voicemail/' . date('YmdHis') . '_' . preg_replace('/\D/', '', $callerId);

agi_say("Por favor deja tu mensaje después del tono. Cuando termines presiona almohadilla.");

// AGI command: Record file
echo "RECORD FILE {$filename} wav # 60 0 1\n";
fgets(STDIN);

agi_say("Mensaje recibido. Te devolveremos la llamada pronto.");

// Notificar a Kivox
kivox_api_post('/ivr/voicemail', [
    'caller_id' => $callerId,
    'archivo'   => $filename . '.wav',
    'timestamp' => date('c'),
]);

exit(0);
