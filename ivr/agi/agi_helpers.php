<?php
/**
 * Helpers compartidos para los scripts AGI.
 *
 * Carga config de env:
 *   KIVOX_API_URL  (ej. https://admin.kivox.co/api/v1/ivr)
 *   KIVOX_API_KEY  (header X-API-KEY)
 */

define('KIVOX_API_URL', getenv('KIVOX_API_URL') ?: 'https://admin.kivox.co/api/v1');
define('KIVOX_API_KEY', getenv('KIVOX_API_KEY') ?: 'change-me');

/**
 * Loggea a stderr (Asterisk lo captura en logs)
 */
function agi_log(string $msg): void {
    error_log("[IVR-AGI] {$msg}\n", 3, '/var/log/asterisk/agi.log');
}

/**
 * Envía un mensaje verbose al log de Asterisk
 */
function agi_verbose(string $msg): void {
    echo "VERBOSE \"{$msg}\" 1\n";
    fgets(STDIN);
}

/**
 * Reproduce un texto vía TTS (Polly o pre-grabado).
 * Para producción: pre-genera audios con OpenAI TTS y guárdalos en /sounds/custom.
 * Esta función básica usa 'SAY ALPHA' como fallback (deletrea, no es TTS real).
 *
 * Mejor: cachea el TTS por hash y stream_file.
 */
function agi_say(string $texto): void {
    $hash = md5($texto);
    $audioPath = "/var/lib/asterisk/sounds/custom/tts_{$hash}";

    if (!file_exists("{$audioPath}.wav")) {
        generar_tts_openai($texto, "{$audioPath}.wav");
    }

    echo "STREAM FILE custom/tts_{$hash} \"\"\n";
    fgets(STDIN);
}

/**
 * Genera un audio TTS con OpenAI y lo guarda como WAV 8kHz mono.
 * Requiere ffmpeg instalado en el container.
 */
function generar_tts_openai(string $texto, string $destPathWav): void {
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        agi_log("ERROR: OPENAI_API_KEY no definido — no se puede generar TTS");
        return;
    }

    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'tts-1-hd',
            'voice' => 'nova',         // español natural femenino
            'input' => $texto,
            'response_format' => 'mp3',
            'speed' => 0.95,
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);

    $mp3 = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$mp3) {
        agi_log("ERROR TTS OpenAI: HTTP {$code}");
        return;
    }

    $tmpMp3 = $destPathWav . '.tmp.mp3';
    file_put_contents($tmpMp3, $mp3);

    // Convertir a WAV 8kHz mono PCM (formato que Asterisk reproduce bien por SIP)
    exec("ffmpeg -y -i {$tmpMp3} -ar 8000 -ac 1 -sample_fmt s16 {$destPathWav} 2>&1", $out, $rc);
    unlink($tmpMp3);

    if ($rc !== 0) {
        agi_log("ERROR ffmpeg: " . implode("\n", $out));
    }
}

/**
 * POST JSON a la API de Kivox
 */
function kivox_api_post(string $endpoint, array $payload) {
    $ch = curl_init(rtrim(KIVOX_API_URL, '/') . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . KIVOX_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 8,
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 400) {
        agi_log("Kivox API POST {$endpoint} → HTTP {$code}: {$body}");
        return null;
    }
    return json_decode($body);
}

/**
 * GET a la API de Kivox
 */
function kivox_api_get(string $endpoint) {
    $ch = curl_init(rtrim(KIVOX_API_URL, '/') . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . KIVOX_API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 8,
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 400) {
        agi_log("Kivox API GET {$endpoint} → HTTP {$code}");
        return null;
    }
    return json_decode($body);
}

/**
 * Normaliza teléfono colombiano a E.164 (+57XXXXXXXXXX)
 */
function normalizar_telefono(string $tel): string {
    $tel = preg_replace('/\D/', '', $tel);
    if (strlen($tel) === 10 && $tel[0] === '3') return '+57' . $tel;
    if (strlen($tel) === 12 && substr($tel, 0, 2) === '57') return '+' . $tel;
    return $tel[0] === '+' ? $tel : '+' . $tel;
}
