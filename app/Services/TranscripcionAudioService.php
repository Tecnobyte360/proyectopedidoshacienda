<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Transcribe audios de WhatsApp usando OpenAI Whisper.
 *
 * Uso:
 *   $texto = app(TranscripcionAudioService::class)->transcribir($urlDelAudio);
 *   // "hola, quiero pedir 2 libras de pechuga"
 *
 * Soporta los formatos que WhatsApp manda típicamente:
 *   .ogg / .opus (voz grabada)
 *   .mp3 / .m4a / .wav (audios enviados como archivo)
 */
class TranscripcionAudioService
{
    /** Duración máxima permitida (segundos). Whisper acepta hasta 25 MB. */
    private int $maxSegundos = 120;

    /**
     * Resuelve la API key a usar para Whisper:
     *   1. La del tenant actual (tenants.openai_api_key)
     *   2. Fallback al .env global
     * Se resuelve EN CADA CALL para respetar el tenant activo del request.
     */
    private function resolverApiKey(): string
    {
        return (string) (\App\Models\Tenant::resolverOpenaiKey() ?? '');
    }

    /**
     * Descarga el audio desde URL y lo transcribe con Whisper.
     *
     * @param  string  $audioUrl  URL HTTP/HTTPS del audio (la que manda TecnoByteApp).
     * @param  string  $idioma    'es' por defecto (mejora exactitud).
     * @return string             Texto transcrito, o '' si falla.
     */
    public function transcribir(string $audioUrl, string $idioma = 'es'): string
    {
        $apiKey = $this->resolverApiKey();
        if ($apiKey === '') {
            Log::error('🎤 OpenAI key no resuelta (ni del tenant ni del .env). No se puede transcribir.');
            return '';
        }

        try {
            // 1. Descargar el audio a un archivo temporal
            $audioData = Http::timeout(30)->get($audioUrl);

            if (!$audioData->successful()) {
                Log::warning('🎤 No pude descargar el audio', [
                    'url'    => $audioUrl,
                    'status' => $audioData->status(),
                ]);
                return '';
            }

            $bytes = $audioData->body();

            // Limite Whisper: 25 MB
            if (strlen($bytes) > 25 * 1024 * 1024) {
                Log::warning('🎤 Audio > 25 MB, se descarta', ['bytes' => strlen($bytes)]);
                return '';
            }

            // 2. Guardar en tmp
            $nombreTmp = 'whisper-' . uniqid() . '.' . $this->extensionDesdeUrl($audioUrl);
            $pathTmp   = storage_path("app/tmp/{$nombreTmp}");
            @mkdir(dirname($pathTmp), 0775, true);
            file_put_contents($pathTmp, $bytes);

            // 3. Enviar a Whisper
            $resp = Http::withToken($apiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($pathTmp), $nombreTmp)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model'    => 'whisper-1',
                    'language' => $idioma,
                    // Response simple para solo obtener el texto
                    'response_format' => 'text',
                ]);

            // 4. Limpiar tmp
            @unlink($pathTmp);

            if (!$resp->successful()) {
                Log::error('🎤 Whisper devolvió error', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
                return '';
            }

            // response_format=text devuelve texto plano (no JSON)
            $texto = trim($resp->body());

            Log::info('🎤 Audio transcrito', [
                'url'       => $audioUrl,
                'largo'     => strlen($texto),
                'preview'   => mb_substr($texto, 0, 120),
            ]);

            return $texto;
        } catch (\Throwable $e) {
            Log::error('🎤 Excepción al transcribir audio: ' . $e->getMessage(), [
                'url'   => $audioUrl,
                'trace' => $e->getTraceAsString(),
            ]);
            return '';
        }
    }

    /** Extrae extensión de la URL, default "ogg" que es el de WhatsApp. */
    private function extensionDesdeUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $permitidas = ['ogg', 'opus', 'mp3', 'm4a', 'wav', 'webm', 'mp4', 'mpga'];
        return in_array($ext, $permitidas, true) ? $ext : 'ogg';
    }
}
